<?php

namespace App\Http\Controllers;

use App\Exceptions\UnacceptableConditionException;
use App\Lib\GrantPasses;
use App\Lib\Reports\ClaimedTicketsWithNoSignups;
use App\Lib\Reports\EarlyArrivalReport;
use App\Lib\Reports\UnclaimedTicketsWithSignupsReport;
use App\Lib\TicketingManagement;
use App\Models\AccessDocument;
use App\Models\Person;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AccessDocumentController extends ApiController
{
    /**
     * Retrieve a access document list
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $query = request()->validate([
            'year' => 'sometimes|digits:4',
            'person_id' => 'sometimes|numeric',
            'status' => 'sometimes|string',
            'type' => 'sometimes|string',
            'include_person' => 'sometimes|boolean',
        ]);

        $this->authorize('index', [AccessDocument::class, $query['person_id'] ?? 0]);

        return $this->success(AccessDocument::findForQuery($query), null, 'access_document');
    }

    /**
     * Retrieve all current/active access documents (excluding appreciations - meals, showers, etc.)
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function current(): JsonResponse
    {
        $this->authorize('current', AccessDocument::class);
        $params = request()->validate([
            'for_delivery' => 'sometimes|boolean',
        ]);

        $forDelivery = $params['for_delivery'] ?? false;

        return response()->json(['documents' => TicketingManagement::retrieveCurrentByPerson($forDelivery)]);
    }

    /**
     * Retrieve all expiring tickets for the current year
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function expiring(): JsonResponse
    {
        $this->authorize('expiring', AccessDocument::class);
        return response()->json(['expiring' => TicketingManagement::retrieveExpiringTicketsByPerson(current_year())]);
    }

    /**
     * Add a comment to a given set of access documents.
     * (primarily used by the export feature to record who did an export.)
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function bulkComment(): JsonResponse
    {
        $this->authorize('bulkComment', AccessDocument::class);
        $params = request()->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'comment' => 'required|string'
        ]);

        $comment = $params['comment'];
        $rows = AccessDocument::whereIntegerInRaw('id', $params['ids'])
            ->get();

        foreach ($rows as $row) {
            $row->addComment($comment, $this->user);
            $row->saveWithoutValidation();
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Mark a list of claimed documents as submitted. Intended to run immediately after
     * the CSV upload was done.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function markSubmitted(): JsonResponse
    {
        $this->authorize('markSubmitted', AccessDocument::class);
        $params = request()->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        $rows = AccessDocument::whereIntegerInRaw('id', $params['ids'])
            ->where('status', AccessDocument::CLAIMED)
            ->get();

        $staffCredentials = $rows->where('type', AccessDocument::STAFF_CREDENTIAL)->keyBy('person_id');

        foreach ($rows as $row) {
            $comment = "bulk marked submitted\ndelivery ";
            if ($row->type == AccessDocument::VEHICLE_PASS_GIFT
                && $staffCredentials->has($row->person_id)) {
                $comment .= 'w/Staff Credential';
            } else {
                $comment .= $row->delivery_method;
            }

            $row->additional_comments = $comment;
            $row->status = AccessDocument::SUBMITTED;
            $row->saveWithoutValidation();
        }

        return $this->success();
    }

    /**
     * Show single Access Document
     *
     * @param AccessDocument $accessDocument
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(AccessDocument $accessDocument): JsonResponse
    {
        $this->authorize('index', [AccessDocument::class, $accessDocument->person_id]);
        return $this->success($accessDocument);
    }

    /**
     * Create an access document
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('create', AccessDocument::class);

        $accessDocument = new AccessDocument;
        $this->fromRest($accessDocument);

        $accessDocument->created_at = $accessDocument->updated_at = now();
        if (!$accessDocument->save()) {
            return $this->restError($accessDocument);
        }

        return $this->success($accessDocument);
    }

    /**
     * Update an Access Document and log any changes
     *
     * @param AccessDocument $accessDocument
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function update(AccessDocument $accessDocument): JsonResponse
    {
        $this->authorize('update', $accessDocument);
        $this->fromRest($accessDocument);

        if (!$accessDocument->save()) {
            return $this->restError($accessDocument);
        }

        return $this->success($accessDocument);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param AccessDocument $accessDocument
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws Exception
     */

    public function destroy(AccessDocument $accessDocument): JsonResponse
    {
        $this->authorize('destroy', $accessDocument);

        $accessDocument->delete();

        return $this->restDeleteSuccess();
    }

    /**
     * Retrieve the change log for a given access document.
     *
     * @param AccessDocument $accessDocument
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function changes(AccessDocument $accessDocument): JsonResponse
    {
        $this->authorize('changes', $accessDocument);

        $changes = [];
        $rows = $accessDocument->access_document_changes()->with('changer_person:id,callsign')->get();
        foreach ($rows as $row) {
            $changes[] = [
                'person_id' => $row->changer_person_id,
                'callsign' => $row->changer_person->callsign ?? "Deleted #{$row->changer_person_id}",
                'operation' => $row->operation,
                'created_at' => (string)$row->created_at,
                'changes' => $row->changes,
            ];
        }

        return response()->json(['changes' => $changes]);
    }

    /**
     * Set the status for several documents owned by the same person at once.
     *
     * Special attention is given to tickets and vehicle passes. Any update to a ticket
     * will cause a check for all banked tickets, and if so, release the
     * vehicle pass to prevent gaming the system, or unintentionally give the VP to the person.
     *
     * @param AccessDocument $accessDocument
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws UnacceptableConditionException
     */

    public function updateStatus(AccessDocument $accessDocument): JsonResponse
    {
        $params = request()->validate(['status' => 'required|string']);

        $this->authorize('update', $accessDocument);
        $personId = $accessDocument->person_id;

        $rows = AccessDocument::where('person_id', $personId)
            ->whereIn('status', [AccessDocument::QUALIFIED, AccessDocument::CLAIMED, AccessDocument::BANKED])
            ->whereNotIn('type', [AccessDocument::LSD, AccessDocument::VEHICLE_PASS_LSD])
            ->get();

        // Find the document in the collection
        $ad = $rows->firstWhere('id', $accessDocument->id);

        if (!$ad) {
            throw new UnacceptableConditionException("Document not found");
        }

        $status = $params['status'];
        $adType = $ad->type;
        $adStatus = $ad->status;
        switch ($status) {
            case AccessDocument::BANKED:
                if (!$ad->isRegularTicket() || !in_array($adStatus, AccessDocument::ACTIVE_STATUSES)) {
                    throw new UnacceptableConditionException('Illegal type and status combination');
                }
                break;

            case AccessDocument::CLAIMED:
                if ($adStatus != AccessDocument::QUALIFIED
                    && $adStatus != AccessDocument::BANKED
                    && $adStatus != AccessDocument::CLAIMED) {
                    throw new UnacceptableConditionException('Document is not banked, claimed, or qualified');
                }
                if ($ad->isRegularTicket()) {
                    // Bank the other tickets
                    $otherTickets = $rows->where('id', '!=', $ad->id)
                        ->whereIn('type', [AccessDocument::STAFF_CREDENTIAL, AccessDocument::SPT]);
                    foreach ($otherTickets as $ticket) {
                        $ticket->status = AccessDocument::BANKED;
                        $ticket->auditReason = 'other ticket claimed';
                        $ticket->saveWithoutValidation();
                    }
                    $passes = $rows->whereIn('type', [AccessDocument::VEHICLE_PASS_SP, AccessDocument::VEHICLE_PASS_GIFT]);
                    foreach ($passes as $pass) {
                        // Release the vehicle passes
                        if ($ad->type == AccessDocument::STAFF_CREDENTIAL && $pass->type == AccessDocument::VEHICLE_PASS_SP) {
                            $pass->status = AccessDocument::QUALIFIED;
                            $pass->auditReason = 'staff credential claimed - released SP VP';
                            $pass->saveWithoutValidation();
                        } else if ($ad->type == AccessDocument::SPT && $pass->type == AccessDocument::VEHICLE_PASS_GIFT) {
                            $pass->status = AccessDocument::QUALIFIED;
                            $pass->auditReason = 'SP ticket claimed - released Gift VP';
                            $pass->saveWithoutValidation();
                        }
                    }
                } else if ($ad->type == AccessDocument::VEHICLE_PASS_GIFT || $ad->type == AccessDocument::VEHICLE_PASS_SP) {
                    // Release the other vehicle passes
                    $otherPasses = $rows->where('id', '!=', $ad->id)
                        ->whereIn('type', [AccessDocument::VEHICLE_PASS_SP, AccessDocument::VEHICLE_PASS_GIFT]);
                    foreach ($otherPasses as $pass) {
                        $pass->status = AccessDocument::QUALIFIED;
                        $pass->auditReason = 'other vehicle pass claimed';
                        $pass->saveWithoutValidation();
                    }
                }
                break;

            case AccessDocument::QUALIFIED:
                if ($adType != AccessDocument::WAP
                    && $adType != AccessDocument::VEHICLE_PASS_GIFT
                    && $adType != AccessDocument::VEHICLE_PASS_SP
                    && $adType != AccessDocument::GIFT) {
                    throw new UnacceptableConditionException('Document is not a WAP, Vehicle Pass nor Gift Ticket.');
                }

                if ($adStatus != AccessDocument::CLAIMED) {
                    throw new UnacceptableConditionException('Document is not claimed.');
                }
                break;

            default:
                throw new UnacceptableConditionException('Unknown status action');
        }

        $ad->status = $status;
        $ad->saveWithoutValidation();

        // Prevent people from trying to game the system and grab the VP without claiming any tickets.
        if (AccessDocument::noAvailableTickets($personId)) {
            $passes = $rows->where('status', AccessDocument::CLAIMED)
                ->whereIn('type', [AccessDocument::VEHICLE_PASS_GIFT, AccessDocument::VEHICLE_PASS_SP]);
            foreach ($passes as $vp) {
                $vp->status = AccessDocument::QUALIFIED;
                $vp->auditReason = 'all tickets were banked';
                $vp->saveWithoutValidation();
            }
        }

        return $this->success($rows, null, 'access_document');
    }


    /**
     * Grant Work Access Passes to people who don't already have them.
     * Criteria are that you have worked in the last three events (not years), are
     * of status active, inactive, or vintage OR... (this is the UNION below)
     * they have signed up for something (training, whatever),
     * AND they don't have a current staff credential or other WAP
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function grantWAPs(): JsonResponse
    {
        $this->authorize('grantWAPs', AccessDocument::class);
        list ($people, $startYear) = GrantPasses::grantWAPsToRangers();
        return response()->json(['people' => $people, 'start_year' => $startYear]);
    }

    /**
     * Find Rangers who might need a WAP.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function wapCandidates(): JsonResponse
    {
        $this->authorize('wapCandidates', [AccessDocument::class]);
        list ($people, $startYear) = GrantPasses::findRangersWhoNeedWAPs();
        return response()->json(['people' => $people, 'start_year' => $startYear]);
    }

    /**
     * Grant Work Access Passes to alphas or prospectives who don't already have them.
     * Criteria are that they are ( (1) an alpha OR (2) a prospective who has signed up
     * for a future training ), AND (3) they don't already have a WAP.
     *
     * NOTE: We set the status on these WAPS to "claimed", not "qualified", because
     * we don't want to make the alphas have to log in and claim them.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function grantAlphaWAPs(): JsonResponse
    {
        $this->authorize('grantAlphaWAPs', [AccessDocument::class]);
        $people = GrantPasses::grantWAPsToAlphas();
        return response()->json(['people' => $people]);
    }

    /**
     * Grant Vehicle Passes to anyone who has a Staff Credential or
     * a Special Price Ticket and who doesn't already have a VP.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function grantVehiclePasses(): JsonResponse
    {
        $this->authorize('grantVehiclePasses', [AccessDocument::class]);
        $people = GrantPasses::grantVehiclePasses();
        return response()->json(['people' => $people]);
    }

    /**
     * Set access dates to default access date for anyone who has a
     * Staff Credential.  Ignores SCs that already have dates.
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function setStaffCredentialsAccessDate(): JsonResponse
    {
        $this->authorize('setStaffCredentialsAccessDate', [AccessDocument::class]);

        $accessDate = setting('TAS_DefaultWAPDate', true);

        $user = $this->user->callsign;

        $rows = AccessDocument::where('type', AccessDocument::STAFF_CREDENTIAL)
            ->whereIn('status', [AccessDocument::BANKED, AccessDocument::CLAIMED, AccessDocument::QUALIFIED])
            ->where(function ($w) {
                $w->whereNull('access_date');
                $w->orWhereYear('access_date', '!=', current_year());
            })
            ->where('access_any_time', false)
            ->with('person:id,callsign,status')
            ->get();
        $rows = $rows->sortBy('person.callsign', SORT_NATURAL | SORT_FLAG_CASE);

        $documents = [];
        foreach ($rows as $row) {
            if ($row->person->status == Person::DISMISSED
                || $row->person->status == Person::DECEASED) {
                continue;
            }
            $row->access_date = $accessDate;
            $row->addComment("changed access date to $accessDate via maintenance function", $user);
            $this->saveAccessDocument($row, $documents);
        }

        return response()->json(['access_documents' => $documents, 'access_date' => $accessDate]);
    }

    /**
     * Clean access docs from prior event.  In particular:
     * Mark "qualified" VPs, WAPs, and SOWAPs as "expired."
     * Mark "submitted" Access Documents of any sort as "used."
     * This does NOT expire RPTs, Gift Tickets, or Staff Creds,
     * see expireAccessDocs below for that.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function cleanAccessDocsFromPriorEvent(): JsonResponse
    {
        $this->authorize('cleanAccessDocsFromPriorEvent', [AccessDocument::class]);

        $user = $this->user->callsign;
        $rows = AccessDocument::whereIn('status', [AccessDocument::SUBMITTED, AccessDocument::QUALIFIED])
            ->with('person:id,callsign,status')
            ->get();

        $documents = [];
        $reasonExpired = 'marked as expired via maintenance function';
        $reasonUsed = 'marked as used via maintenance function';
        foreach ($rows as $ad) {
            switch ($ad->status) {
                case AccessDocument::QUALIFIED:
                    if ($ad->doesExpireThisYear()) {
                        $ad->status = AccessDocument::EXPIRED;
                        $ad->addComment($reasonExpired, $user);
                        $ad->auditReason = $reasonExpired;
                        $this->saveAccessDocument($ad, $documents);
                    }
                    break;

                case AccessDocument::SUBMITTED:
                    $ad->status = AccessDocument::USED;
                    $ad->addComment($reasonUsed, $user);
                    $ad->auditReason = $reasonUsed;
                    $this->saveAccessDocument($ad, $documents);
                    break;
            }
        }

        usort($documents, fn($a, $b) => strcasecmp($a['person']['callsign'], $b['person']['callsign']));

        return response()->json(['access_documents' => $documents]);
    }

    /**
     * Mark any qualified tickets/credentials as banked.
     * For staff credentials, reset access_date.
     * In addition, reset access_dates to null as needed
     * We don't check expiration here, that's handled elsewhere.
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function bankAccessDocuments(): JsonResponse
    {
        $this->authorize('bankAccessDocuments', [AccessDocument::class]);

        $user = $this->user->callsign;

        $rows = AccessDocument::where('status', AccessDocument::QUALIFIED)
            ->whereIn('type', AccessDocument::TICKET_TYPES)
            ->with('person:id,callsign,status')
            ->get();


        $documents = [];
        foreach ($rows as $ad) {
            if ($ad->type == AccessDocument::STAFF_CREDENTIAL) {
                $ad->access_date = null;
                $ad->access_any_time = false;
            }

            $ad->status = AccessDocument::BANKED;
            $ad->addComment('marked as banked via maintenance function', $user);
            $this->saveAccessDocument($ad, $documents);
        }

        // The below code is kind of a hack and probably doesn't
        // really belong in this function

        $rows = AccessDocument::where('status', AccessDocument::BANKED)
            ->where('type', AccessDocument::STAFF_CREDENTIAL)
            ->where(function ($q) {
                $q->whereNotNull('access_date');
                $q->orWhere('access_any_time', true);
            })
            ->with('person:id,callsign,status')
            ->get();

        foreach ($rows as $ad) {
            $ad->access_date = null;
            $ad->access_any_time = false;
            $ad->addComment('set access date to unspecified via maintenance function', $user);
            $this->saveAccessDocument($ad, $documents);
        }

        usort($documents, fn($a, $b) => strcasecmp($a['person']['callsign'], $b['person']['callsign']));

        return response()->json(['access_documents' => $documents]);
    }

    /**
     * Expire old access documents.
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function expireAccessDocuments(): JsonResponse
    {
        $this->authorize('expireAccessDocuments', [AccessDocument::class]);

        $user = $this->user->callsign;

        $rows = AccessDocument::whereIn('status', [AccessDocument::BANKED, AccessDocument::CLAIMED, AccessDocument::QUALIFIED])
            ->whereRaw('expiry_date < ?', [now()])
            ->with('person:id,callsign,status,email')
            ->get();

        $documents = [];
        foreach ($rows as $ad) {
            $ad->status = AccessDocument::EXPIRED;
            $ad->addComment('marked as expired via maintenance function', $user);
            $this->saveAccessDocument($ad, $documents, true);
        }

        usort($documents, fn($a, $b) => strcasecmp($a['person']['callsign'], $b['person']['callsign']));

        return response()->json(['access_documents' => $documents]);
    }

    /**
     * Bump all banked and qualified tickets' expiration by 1 year.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function bumpExpiration(): JsonResponse
    {
        $this->authorize('bumpExpiration', AccessDocument::class);
        $params = request()->validate([
            'reason' => 'sometimes|string'
        ]);

        $reason = $params['reason'] ?? null;

        $callsign = $this->user->callsign;

        $rows = AccessDocument::whereIn('status', [AccessDocument::BANKED, AccessDocument::QUALIFIED])
            ->get();

        foreach ($rows as $row) {
            $row->expiry_date = $row->expiry_date->addYear();
            if ($reason) {
                $row->addComment($reason, $callsign);
            }

            $row->save();
        }

        return response()->json(['count' => $rows->count()]);
    }

    /**
     * Save the access document, log the changes, and build a response.
     *
     * @param AccessDocument $ad
     * @param $documents
     * @param bool $includeEmail
     * @throws ValidationException
     */

    private function saveAccessDocument(AccessDocument $ad, &$documents, bool $includeEmail = false): void
    {
        $ad->save();

        $person = $ad->person;
        $result = [
            'id' => $ad->id,
            'type' => $ad->type,
            'status' => $ad->status,
            'source_year' => $ad->source_year,
            'person' => [
                'id' => $ad->person_id,
                'callsign' => $person ? $person->callsign : 'Person #' . $ad->person_id,
                'status' => $person ? $person->status : 'unknown',
            ]
        ];

        if ($includeEmail && $person) {
            $result['person']['email'] = $person->email;
        }

        $documents[] = $result;
    }

    /**
     * Find all banked items and set the status to qualified.
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function unbankAccessDocuments(): JsonResponse
    {
        $this->authorize('unbankAccessDocuments', AccessDocument::class);

        $rows = AccessDocument::where('status', AccessDocument::BANKED)
            ->with('person:id,callsign,status')
            ->get();

        $documents = [];
        foreach ($rows as $row) {
            $row->auditReason = 'maintenance - unbank items';
            $row->status = AccessDocument::QUALIFIED;
            $row->addComment('marked as qualified via maintenance function', $this->user->callsign);
            $row->saveWithoutValidation();
            $this->saveAccessDocument($row, $documents, true);
        }

        usort($documents, fn($a, $b) => strcasecmp($a['person']['callsign'], $b['person']['callsign']));

        return response()->json(['access_documents' => $documents]);
    }

    /**
     * Find all unclaimed tickets and people who may or may not be signed up
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function unclaimedTicketsWithSignups(): JsonResponse
    {
        $this->authorize('unclaimedTicketsWithSignups', AccessDocument::class);

        return response()->json(['tickets' => UnclaimedTicketsWithSignupsReport::execute()]);
    }

    /**
     * Report on claimed tickets yet the person has not signed up for anything.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function claimedTicketsWithNoSignups(): JsonResponse
    {
        $this->authorize('claimedTicketsWithNoSignups', AccessDocument::class);
        return response()->json(['people' => ClaimedTicketsWithNoSignups::execute()]);
    }

    /**
     * Report on special tickets
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function specialTicketsReport(): JsonResponse
    {
        $this->authorize('specialTicketsReport', AccessDocument::class);

        $rows = AccessDocument::whereIn('type', [...AccessDocument::SPECIAL_TICKET_TYPES, ...AccessDocument::SPECIAL_VP_TYPES])
            ->whereIn('status', [
                AccessDocument::CLAIMED,
                AccessDocument::QUALIFIED,
                AccessDocument::SUBMITTED,
                AccessDocument::TURNED_DOWN,
            ])
            ->with('person:id,callsign,status')
            ->get();

        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'id' => $row->id,
                'type' => $row->type,
                'status' => $row->status,
                'person' => [
                    'id' => $row->person->id,
                    'callsign' => $row->person->callsign,
                    'status' => $row->person->status,
                ]
            ];
        }

        usort($results, fn($a, $b) => strcasecmp($a['person']['callsign'], $b['person']['callsign']));
        return response()->json(['access_documents' => $results]);
    }

    /**
     * Early Arrival Report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function earlyArrivalReport(): JsonResponse
    {
        $this->authorize('earlyArrivalReport', AccessDocument::class);

        return response()->json(EarlyArrivalReport::execute(current_year()));
    }
}

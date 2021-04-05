<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;

use Exception;
use InvalidArgumentException;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

use App\Models\AccessDocument;
use App\Models\AccessDocumentChanges;
use App\Models\AccessDocumentDelivery;
use App\Models\Person;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\Slot;
use App\Models\Timesheet;

class AccessDocumentController extends ApiController
{
    /**
     * Retrieve a access document list
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index()
    {
        $query = request()->validate([
            'year' => 'sometimes|digits:4',
            'person_id' => 'sometimes|numeric',
            'status' => 'sometimes|string',
            'type' => 'sometimes|string',
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

    public function current()
    {
        $this->authorize('current', AccessDocument::class);
        $params = request()->validate([
            'for_delivery' => 'sometimes|boolean',
        ]);

        $forDelivery = $params['for_delivery'] ?? false;

        return response()->json(['documents' => AccessDocument::retrieveCurrentByPerson($forDelivery)]);
    }

    /**
     * Retrieve all expiring tickets for the current year
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function expiring()
    {
        $this->authorize('expiring', AccessDocument::class);
        return response()->json(['expiring' => AccessDocument::retrieveExpiringTicketsByPerson(current_year())]);
    }

    /**
     * Mark a list of claimed documents as submitted. Intended to run immediately after
     * the CSV upload was done.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function markSubmitted()
    {
        $this->authorize('markSubmitted', AccessDocument::class);
        $params = request()->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        $rows = AccessDocument::whereIn('id', $params['ids'])
            ->where('status', AccessDocument::CLAIMED)
            ->whereNotIn('type', AccessDocument::PROVISION_TYPES)
            ->get();

        if ($rows->isNotEmpty()) {
            $deliveries = AccessDocumentDelivery::retrieveForPersonIdsYear($rows->pluck('person_id'), current_year());
        }

        $staffCredentials = $rows->where('type', AccessDocument::STAFF_CREDENTIAL)->keyBy('person_id');

        foreach ($rows as $row) {
            $comment = "bulk marked submitted\ndelivery ";
            $delivery = $deliveries->get($row->person_id);
            if ($row->type == AccessDocument::VEHICLE_PASS && $staffCredentials->has($row->person_id)) {
                $comment .= 'w/Staff Credential';
            } else if ($delivery) {
                $comment .= $delivery->method;
                if ($delivery->method == AccessDocumentDelivery::MAIL) {
                    $comment .= "mail {$delivery->street}, {$delivery->city}, {$delivery->state} {$delivery->postal_code}";
                }
            } else {
                $comment .= 'none';
            }

            $row->additional_comments = $comment;
            $oldStatus = $row->status;
            $row->status = AccessDocument::SUBMITTED;
            $row->saveWithoutValidation();
            AccessDocumentChanges::log($row, $this->user->id, ['status' => [$oldStatus, AccessDocument::SUBMITTED]]);
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
    public function show(AccessDocument $accessDocument)
    {
        $this->authorize('index', [AccessDocument::class, $accessDocument->person_id]);
        return $this->success($accessDocument);
    }

    /**
     * Create an access document
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function store()
    {
        $this->authorize('create', AccessDocument::class);

        $accessDocument = new AccessDocument;
        $this->fromRest($accessDocument);

        $accessDocument->create_date = $accessDocument->modified_date = now();
        if (!$accessDocument->save()) {
            return $this->restError($accessDocument);
        }

        AccessDocumentChanges::log($accessDocument, $this->user->id, $accessDocument, 'create');

        return $this->success($accessDocument);
    }

    /**
     * Update an Access Document and log any changes
     *
     * @param AccessDocument $accessDocument
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function update(AccessDocument $accessDocument)
    {
        $this->authorize('update', $accessDocument);
        $this->fromRest($accessDocument);

        $changes = $accessDocument->getChangedValues();

        if (!$accessDocument->save()) {
            return $this->restError($accessDocument);
        }

        if (!empty($changes)) {
            AccessDocumentChanges::log($accessDocument, $this->user->id, $changes);
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

    public function destroy(AccessDocument $accessDocument)
    {
        $this->authorize('destroy', $accessDocument);

        $accessDocument->delete();
        AccessDocumentChanges::log($accessDocument, $this->user->id, $accessDocument, 'delete');

        return $this->restDeleteSuccess();
    }

    /**
     * Set the status for several documents owned by the same person at once.
     *
     * Special attention is given to tickets and vehicle passes. Any update to a ticket
     * will cause a check for all banked tickets, and if so, release the
     * vehicle pass to prevent gaming the system, or unintentionally give the VP to the person.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function statuses()
    {
        $params = request()->validate([
            'statuses.*.id' => 'required|integer|exists:access_document,id',
            'statuses.*.status' => 'required|string'
        ]);

        $rows = AccessDocument::whereIn('id', array_column($params['statuses'], 'id'))->get();

        $personId = $rows[0]->person_id;
        foreach ($rows as $row) {
            // Verify person can update all the documents.
            $this->authorize('update', $row);
            if ($personId != $row->person_id) {
                throw new \InvalidArgumentException("All records must be for the same person");
            }
        }

        $docsById = $rows->keyBy('id');

        $haveTicket = false;

        foreach ($params['statuses'] as $statusUpdate){
            $status = $statusUpdate['status'];
            $ad = $docsById->get($statusUpdate['id']);
            $adType = $ad->type;
            $adStatus = $ad->status;
            switch ($status) {
                case AccessDocument::BANKED:
                    if ((!in_array($adType, AccessDocument::TICKET_TYPES)
                            && !in_array($adType, AccessDocument::PROVISION_TYPES))
                        || !in_array($adStatus, AccessDocument::ACTIVE_STATUSES)) {
                        throw new InvalidArgumentException('Illegal type and status combination');
                    }
                    break;

                case AccessDocument::CLAIMED:
                    if ($adStatus != AccessDocument::QUALIFIED && $adStatus != AccessDocument::BANKED) {
                        throw new InvalidArgumentException('Document is not banked or qualified');
                    }
                    break;

                case AccessDocument::QUALIFIED:
                    if ($adType != AccessDocument::WAP
                        && $adType != AccessDocument::VEHICLE_PASS
                        && !in_array($adType, AccessDocument::PROVISION_TYPES)) {
                        throw new InvalidArgumentException('Document is not a WAP, Vehicle Pass, or an Appreciation.');
                    }

                    if ($adStatus != AccessDocument::CLAIMED) {
                        throw new InvalidArgumentException('Document is not claimed.');
                    }
                    break;

                default:
                    throw new InvalidArgumentException('Unknown status action');
            }

            $ad->status = $status;

            $changes = $ad->getChangedValues();

            if (!empty($changes)) {
                $ad->saveWithoutValidation();
                $changes['id'] = $ad->id;
                AccessDocumentChanges::log($ad, $this->user->id, $changes);
            }

            if ($ad->isTicket()) {
                $haveTicket = true;
            }
        }


        if ($haveTicket) {
            // Prevent people from trying to game the system and grab the VP without claiming any tickets.
            $personId = $rows[0]->person_id;
            if (AccessDocument::noAvailableTickets($personId)) {
                $vp = AccessDocument::where([
                    'person_id' => $personId,
                    'type' => AccessDocument::VEHICLE_PASS,
                    'status' => AccessDocument::CLAIMED
                ])->first();
                if ($vp) {
                    $vp->status = AccessDocument::QUALIFIED;
                    $vp->auditReason = 'All tickets were banked';
                    $vp->saveWithoutValidation();
                    AccessDocumentChanges::log($vp, $this->user->id,
                        [ 'status' => [AccessDocument::CLAIMED, AccessDocument::QUALIFIED ]]
                    );
                }
            }
        }

        return $this->success($rows, null, 'access_document');
    }

    /**
     * Grant Work Access Passes to people who don't already have them.
     * Criteria are that you have worked in the last three years, are
     * of status active, inactive, or vintage OR... (this is the UNION below)
     * they have signed up for something (training, whatever),
     * AND they don't have a current staff credential or other WAP
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function grantWAPs()
    {
        $this->authorize('grantWAPs', [AccessDocument::class]);

        $year = current_year();
        $startYear = $year - 3;

        $accessDate = setting('TAS_DefaultWAPDate', true);

        // Find everyone who worked in the last three years
        $workedIds = Timesheet::select('person_id')
            ->join('person', 'person.id', 'timesheet.person_id')
            ->whereYear('on_duty', '>=', $startYear)
            ->whereIn('status', [Person::ACTIVE, Person::INACTIVE])
            ->groupBy('person_id')
            ->get()
            ->pluck('person_id');

        // .. and find everyone signed up this year.

        $slotIds = Slot::whereYear('begins', $year)->pluck('id');
        $signUpIds = PersonSlot::select('person_id')
            ->join('person', 'person.id', 'person_slot.person_id')
            ->whereIn('slot_id', $slotIds)
            ->whereIn('person.status', Person::ACTIVE_STATUSES)
            ->groupBy('person_slot.person_id')
            ->get()
            ->pluck('person_id');

        $personIds = $signUpIds->merge($workedIds)->unique();
        $people = Person::select('id', 'callsign', 'status')
            ->whereIn('id', $personIds)
            ->whereRaw('
                (NOT EXISTS
                    (SELECT 1 FROM access_document WHERE access_document.person_id=person.id AND type="work_access_pass" AND status IN ("qualified", "claimed", "submitted") LIMIT 1)
                AND
                   (
                     EXISTS
                        (SELECT 1 FROM access_document WHERE access_document.person_id=person.id AND type="reduced_price_ticket" AND status IN ("qualified", "claimed", "banked", "submitted") LIMIT 1)
                    OR
                      NOT EXISTS
                      (SELECT 1 FROM access_document WHERE access_document.person_id=person.id AND type="staff_credential" AND status IN ("qualified", "claimed", "banked", "submitted") LIMIT 1)
                   )
                ) ')
            ->orderBy('callsign')
            ->get();

        $this->grantAccessDocumentToPeople($people, AccessDocument::WAP, null, $year);

        return response()->json(['people' => $people]);
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
    public function grantAlphaWAPs()
    {
        $this->authorize('grantAlphaWAPs', [AccessDocument::class]);

        $year = current_year();

        $accessDate = setting('TAS_DefaultAlphaWAPDate', true);

        // Where be my Alphas yo?
        $alphaIds = Person::select('id')->where('status', Person::ALPHA)->get()->pluck('id');

        // Find all training slots starting on or after today
        $slotIds = Slot::select('id')
            ->whereYear('begins', $year)
            ->where('position_id', Position::TRAINING)
            ->whereRaw('begins > ?', [now()])
            ->get()
            ->pluck('id');

        if (!empty($slotIds)) {
            $prospectiveIds = PersonSlot::select('person_id')
                ->join('person', 'person.id', 'person_slot.person_id')
                ->whereIn('slot_id', $slotIds)
                ->where('status', Person::PROSPECTIVE)
                ->groupBy('person_id')
                ->get()
                ->pluck('person_id');
        } else {
            $prospectiveIds = [];
        }

        $ids = $alphaIds->merge($prospectiveIds)->unique();

        if (!empty($ids)) {
            $people = Person::select('id', 'callsign', 'status')
                ->whereIn('id', $ids)
                ->whereRaw('NOT EXISTS (SELECT 1 FROM access_document WHERE person_id=person.id
                        AND
                           (
                             (access_document.type="work_access_pass" AND access_document.status IN ("qualified", "claimed", "submitted"))
                            OR
                            (access_document.type="staff_credential" AND access_document.status IN ("qualified", "claimed", "banked", "submitted"))
                        ) LIMIT 1)')
                ->orderBy('callsign')
                ->get();
        } else {
            $people = [];
        }

        $this->grantAccessDocumentToPeople($people, AccessDocument::WAP, $accessDate, $year, AccessDocument::CLAIMED);
        return response()->json(['people' => $people]);
    }

    /**
     * Grant Vehicle Passes to anyone who has a staff credential or
     * a reduced-price ticket and who doesn't already have a VP.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function grantVehiclePasses()
    {
        $this->authorize('grantVehiclePasses', [AccessDocument::class]);

        $year = current_year();

        $ids = AccessDocument::select('person_id')
            ->whereIn('type', [AccessDocument::STAFF_CREDENTIAL, AccessDocument::RPT])
            ->whereIn('status', [AccessDocument::QUALIFIED, AccessDocument::CLAIMED, AccessDocument::BANKED])
            ->whereRaw('NOT EXISTS (SELECT 1 FROM access_document ad WHERE ad.person_id=access_document.person_id AND ad.type="vehicle_pass" AND ad.status IN ("qualified", "claimed", "submitted") LIMIT 1)')
            ->groupBy('person_id')
            ->pluck('person_id');

        if ($ids->count()) {
            $people = Person::select('id', 'callsign', 'status')
                ->whereIn('id', $ids)
                ->orderBy('callsign')
                ->get();

            $this->grantAccessDocumentToPeople($people, AccessDocument::VEHICLE_PASS, null, $year);
        } else {
            $people = [];
        }

        return response()->json(['people' => $people]);
    }

    /**
     * Set access dates to default access date for anyone who has a
     * Staff Credential.  Ignores SCs that already have dates.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function setStaffCredentialsAccessDate()
    {
        $this->authorize('setStaffCredentialsAccessDate', [AccessDocument::class]);

        $accessDate = setting('TAS_DefaultWAPDate', true);

        $user = $this->user->callsign;

        $rows = AccessDocument::where('type', AccessDocument::STAFF_CREDENTIAL)
            ->whereIn('status', [AccessDocument::BANKED, AccessDocument::CLAIMED, AccessDocument::QUALIFIED])
            ->whereNull('access_date')
            ->where('access_any_time', false)
            ->with('person:id,callsign,status')
            ->get();
        $rows = $rows->sortBy('person.callsign', SORT_NATURAL | SORT_FLAG_CASE);

        $documents = [];
        foreach ($rows as $row) {
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

    public function cleanAccessDocsFromPriorEvent()
    {
        $this->authorize('cleanAccessDocsFromPriorEvent', [AccessDocument::class]);

        $user = $this->user->callsign;
        $rows = AccessDocument::whereIn('status', [AccessDocument::SUBMITTED, AccessDocument::QUALIFIED])
            ->with('person:id,callsign,status')
            ->get();
        $rows = $rows->sortBy('person.callsign', SORT_NATURAL | SORT_FLAG_CASE);

        $documents = [];

        $reasonExpired = 'marked as expired via maintenance function';
        $reasonUsed = 'marked as used via maintenance function';
        foreach ($rows as $ad) {
            switch ($ad->status) {
                case AccessDocument::QUALIFIED:
                    if ($ad->type == AccessDocument::VEHICLE_PASS
                        || $ad->type == AccessDocument::WAP
                        || $ad->type == AccessDocument::WAPSO) {
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

        return response()->json(['access_documents' => $documents]);
    }

    /**
     * Mark any qualified tickets/credentials as banked.
     * For staff credentials, reset access_date.
     * In addition, reset access_dates to null as needed
     * We don't check expiration here, that's handled elsewhere.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function bankAccessDocuments()
    {
        $this->authorize('bankAccessDocuments', [AccessDocument::class]);

        $user = $this->user->callsign;

        $rows = AccessDocument::where('status', AccessDocument::QUALIFIED)
            ->whereIn('type', array_merge(AccessDocument::TICKET_TYPES, AccessDocument::PROVISION_TYPES))
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

        usort($documents, function ($a, $b) {
            return strcasecmp($a['person']['callsign'], $b['person']['callsign']);
        });

        return response()->json(['access_documents' => $documents]);
    }

    /**
     * Expire old access documents.
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function expireAccessDocuments()
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

        usort($documents, function ($a, $b) {
            return strcasecmp($a['person']['callsign'], $b['person']['callsign']);
        });

        return response()->json(['access_documents' => $documents]);
    }

    /**
     * Bump all banked and qualified tickets' expiration by 1 year.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function bumpExpiration()
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

            $changes = $row->getChangedValues();
            $row->save();
            AccessDocumentChanges::log($row, $this->user->id, $changes);
        }

        return response()->json(['count' => $rows->count()]);
    }

    /*
     * Save the access document, log the changes, and build a response.
     */

    private function saveAccessDocument($ad, &$documents, $includeEmail = false)
    {
        $changes = $ad->getChangedValues();
        $ad->save();
        AccessDocumentChanges::log($ad, $this->user->id, $changes);

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

    /*
     * Create a Access Document batch of a particular type & status for folks and log the creation.
     *
     * The assumption is the type will be non-bankable item (vp, wap, etc) and will expire in the current year.
     */

    private function grantAccessDocumentToPeople($people, $type, $accessDate, $year, $status = AccessDocument::QUALIFIED)
    {
        $user = $this->user->callsign;
        $userId = $this->user->id;

        foreach ($people as $person) {
            $ad = new AccessDocument([
                'person_id' => $person->id,
                'type' => $type,
                'status' => $status,
                'source_year' => $year,
                'expiry_date' => "$year-09-15",
                'access_date' => $accessDate,
            ]);
            $ad->addComment('created via maintenance function', $user);
            $ad->saveWithoutValidation();
            AccessDocumentChanges::log($ad, $userId, $ad, 'create');
        }
    }
}

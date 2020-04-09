<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;

use App\Models\AccessDocument;
use App\Models\AccessDocumentChanges;
use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\Role;
use App\Models\Slot;
use App\Models\Timesheet;

use App\Helpers\SqlHelper;

class AccessDocumentController extends ApiController
{
    /*
     * Retrieve a access document list
     */
    public function index()
    {
        $query = request()->validate([
            'year'      => 'sometimes|digits:4',
            'person_id' => 'sometimes|numeric',
        ]);

        $personId = isset($query['person_id']) ? $query['person_id'] : 0;

        $this->authorize('index', [ AccessDocument::class, $personId ]);

        return $this->success(AccessDocument::findForQuery($query), null, 'access_document');
    }

    /*
     * Retrieve all current/active access documents
     */

     public function current()
     {
         $this->authorize('current', AccessDocument::class);
         $params = request()->validate([
             'for_delivery'  => 'sometimes|boolean',
         ]);

         $forDelivery = isset($params['for_delivery']);

         return response()->json([
             'documents'   => AccessDocument::retrieveCurrentByPerson($forDelivery)
         ]);
     }

    /*
     * Retrieve all expiring tickets for the current year
     */

    public function expiring()
    {
        $this->authorize('expiring', AccessDocument::class);

        $year = current_year();

        return response()->json([
            'expiring' => AccessDocument::retrieveExpiringTicketsByPerson($year)
        ]);
    }

    /*
     * Mark a list of claimed documents as submitted
     */

    public function markSubmitted()
    {
        $this->authorize('markSubmitted', AccessDocument::class);
        $params = request()->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        $ids = $params['ids'];

        $rows = AccessDocument::whereIn('id', $ids)
            ->where('status', 'claimed')
            ->get();

        foreach ($rows as $row) {
            $oldStatus = $row->status;
            $row->update([ 'status' => 'submitted' ]);
            AccessDocumentChanges::log($row, $this->user->id, [ 'status' => [ $oldStatus, 'submitted' ] ]);
        }

        return $this->success();
    }

    /**
     * Show single Access Document
     */

    public function show(AccessDocument $accessDocument)
    {
        $this->authorize('index', [ AccessDocument::class, $accessDocument->person_id ]);
        return $this->success($accessDocument);
    }

    /*
     * Create an access document
     */

    public function store(Request $request)
    {
        $this->authorize('create', AccessDocument::class);

        $accessDocument = new AccessDocument;
        $this->fromRest($accessDocument);

        if (!$this->userHasRole([ Role::ADMIN, Role::EDIT_ACCESS_DOCS])) {
            $accessDocument->person_id = $this->user->id;
        }

        $accessDocument->create_date = $accessDocument->modified_date = SqlHelper::now();
        if (!$accessDocument->save()) {
            return $this->restError($accessDocument);
        }

        AccessDocumentChanges::log($accessDocument, $this->user->id, $accessDocument, 'create');

        return $this->success($accessDocument);
    }

    /*
     * update a specific resource.
     */
    public function update(Request $request, AccessDocument $accessDocument)
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
     * @param  \App\AccessDocument $accessDocument
     * @return \Illuminate\Http\Response
     */
    public function destroy(AccessDocument $accessDocument)
    {
        $this->authorize('destroy', $accessDocument);

        $accessDocument->delete();
        AccessDocumentChanges::log($accessDocument, $this->user->id, $accessDocument, 'delete');

        return $this->restDeleteSuccess();
    }

    /*
     * Set the status for the document.
     *
     * Yes, this could be folded into store(), however, this limits the security complexity
     * of having to comb through the data sent in a store request.
     */

    public function status(AccessDocument $accessDocument)
    {
        $this->authorize('update', $accessDocument);

        $request = request()->validate(
            [ 'status' => 'required|string' ]
        );

        $status = $request['status'];

        $adStatus = $accessDocument->status;
        $adType = $accessDocument->type;

        switch ($status) {
            case 'banked':
                if (!in_array($adType, [ 'staff_credential', 'reduced_price_ticket', 'gift_ticket'])
                || !in_array($adStatus, [ 'qualified', 'claimed', 'banked'])) {
                    throw new \InvalidArgumentException('Illegal type and status combination');
                }
                break;

            case 'claimed':
                if ($adStatus != 'qualified' && $adStatus != 'banked') {
                    throw new \InvalidArgumentException('Document is not banked or qualified');
                }
                break;

            case 'qualified':
                if ($adType != 'work_access_pass' && $adType != 'vehicle_pass') {
                    throw new \InvalidArgumentException('Document is not a WAP or vehicle pass');
                }

                if ($adStatus != 'claimed') {
                    throw new \InvalidArgumentException('Document is not claimed.');
                }
                break;

            default:
                throw new \InvalidArgumentException('Unknown status action');
                break;
        }

        $accessDocument->status = $status;
        $changes = $accessDocument->getChangedValues();

        if (!empty($changes)) {
            $accessDocument->saveWithoutValidation();
            $changes['id'] = $accessDocument->id;
            AccessDocumentChanges::log($accessDocument, $this->user->id, $changes);
        }

        return $this->success();
    }

    /*
     * Grant Work Access Passes to people who don't already have them.
     * Criteria are that you have worked in the last three years, are
     * of status active, inactive, or vintage OR... (this is the UNION below)
     * they have signed up for something (training, whatever),
     * AND they don't have a current staff credential or other WAP
     */

    public function grantWAPs()
    {
        $this->authorize('grantWAPs', [ AccessDocument::class ]);

        $year = current_year();
        $startYear = $year - 3;

        $accessDate = setting('TAS_DefaultWAPDate');
        if (empty($accessDate)) {
            throw new \InvalidArgumentException('TAS_DefaultWAPDate is not configured.');
        }

        // Find everyone who worked in the last three years
        $workedIds = Timesheet::select('person_id')
                        ->join('person', 'person.id', 'timesheet.person_id')
                        ->whereYear('on_duty', '>=', $startYear)
                        ->whereIn('status', [ Person::ACTIVE, Person::INACTIVE ])
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

        $this->grantAccessDocumentToPeople($people, 'work_access_pass', null, $year);

        return response()->json([ 'people' => $people ]);
    }

    /*
     * Grant Work Access Passes to alphas or prospectives who don't already have them.
     * Criteria are that they are ( (1) an alpha OR (2) a prospective who has signed up
     * for a future training ), AND (3) they don't already have a WAP.
     *
     * NOTE: We set the status on these WAPS to "claimed", not "qualified", because
     * we don't want to make the alphas have to log in and claim them.
     */

    public function grantAlphaWAPs()
    {
        $this->authorize('grantAlphaWAPs', [ AccessDocument::class ]);

        $year = current_year();

        $accessDate = setting('TAS_DefaultAlphaWAPDate');
        if (empty($accessDate)) {
            throw new \InvalidArgumentException('TAS_DefaultAlphaWAPDate is not configured');
        }

        // Where be my Alphas yo?
        $alphaIds = Person::select('id')->where('status', Person::ALPHA)->get()->pluck('id');

        // Find all training slots starting on or after today
        $slotIds = Slot::select('id')
                    ->whereYear('begins', $year)
                    ->where('position_id', Position::TRAINING)
                    ->whereRaw('begins > CURRENT_DATE()')
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

        $this->grantAccessDocumentToPeople($people, 'work_access_pass', $accessDate, $year, 'claimed');
        return response()->json([ 'people' => $people ]);
    }

    /*
     * Grant Vehicle Passes to nyone who has a staff credential or
     * a reduced-price ticket and who doesn't already have a VP.
     */

    public function grantVehiclePasses()
    {
        $this->authorize('grantVehiclePasses', [ AccessDocument::class ]);

        $year = current_year();

        $ids = AccessDocument::select('person_id')
                ->whereIn('type', [ 'staff_credential', 'reduced_price_ticket'])
                ->whereIn('status', [ 'qualified', 'claimed', 'banked' ])
                ->whereRaw('NOT EXISTS (SELECT 1 FROM access_document ad WHERE ad.person_id=access_document.person_id AND ad.type="vehicle_pass" AND ad.status IN ("qualified", "claimed", "submitted") LIMIT 1)')
                ->groupBy('person_id')
                ->pluck('person_id');

        if ($ids->count()) {
            $people = Person::select('id', 'callsign', 'status')
                    ->whereIn('id', $ids)
                    ->orderBy('callsign')
                    ->get();

            $this->grantAccessDocumentToPeople($people, 'vehicle_pass', null, $year);
        } else {
            $people = [];
        }

        return response()->json([ 'people' => $people ]);
    }

    /*
     * Set access dates to default access date for anyone who has a
     * Staff Credential.  Ignores SCs that already have dates.
      */
    public function setStaffCredentialsAccessDate()
    {
        $this->authorize('setStaffCredentialsAccessDate', [ AccessDocument::class ]);

        $accessDate = setting('TAS_DefaultWAPDate');
        if (empty($accessDate)) {
            throw new \InvalidArgumentException('TAS_DefaultWAPDate is not configured.');
        }

        $user = $this->user->callsign;

        $rows = AccessDocument::where('type', 'staff_credential')
                ->whereIn('status', [ 'banked', 'claimed', 'qualified'])
                ->whereNull('access_date')
                ->where('access_any_time', false)
                ->with('person:id,callsign,status')
                ->get();
        $rows = $rows->sortBy('person.callsign', SORT_NATURAL|SORT_FLAG_CASE);

        $documents = [];
        foreach ($rows as $row) {
            $row->access_date = $accessDate;
            $row->addComment("changed access date to $accessDate via maintenance function", $user);
            $this->saveAccessDocument($row, $documents);
        }

        return response()->json([ 'access_documents' => $documents, 'access_date' => $accessDate ]);
    }

    /*
      * Clean access docs from prior event.  In particular:
      * Mark "qualified" VPs, WAPs, and SOWAPs as "expired."
      * Mark "submitted" Access Documents of any sort as "used."
      * This does NOT expire RPTs, Gift Tickets, or Staff Creds,
      * see expireAccessDocs below for that.
      */
    public function cleanAccessDocsFromPriorEvent()
    {
        $this->authorize('cleanAccessDocsFromPriorEvent', [ AccessDocument::class ]);

        $user = $this->user->callsign;
        $rows = AccessDocument::whereIn('status', [ 'submitted', 'qualified' ])
                  ->with('person:id,callsign,status')
                  ->get();
        $rows = $rows->sortBy('person.callsign', SORT_NATURAL|SORT_FLAG_CASE);

        $documents = [];
        foreach ($rows as $ad) {
            switch ($ad->status) {
            case 'qualified':
              if ($ad->type == 'vehicle_pass'
              || $ad->type == 'work_access_pass'
              || $ad->type == 'work_access_pass_so') {
                  $ad->status = 'expired';
                  $ad->addComment('marked as expired via maintenance function', $user);
                  $this->saveAccessDocument($ad, $documents);
              }
              break;

            case 'submitted':
              $ad->status = 'used';
              $ad->addComment('marked as used via maintenance function', $user);
              $this->saveAccessDocument($ad, $documents);
              break;
            }
        }

        return response()->json([ 'access_documents' => $documents ]);
    }

    /*
     * Mark any qualified tickets/credentials as banked.
     * For staff credentials, reset access_date.
     * In addition, reset access_dates to null as needed
     * We don't check expiration here, that's handled elsewhere.
     */

    public function bankAccessDocuments()
    {
        $this->authorize('bankAccessDocuments', [ AccessDocument::class ]);

        $year = current_year();
        $user = $this->user->callsign;

        $rows = AccessDocument::where('status', 'qualified')
                ->whereIn('type', [ 'reduced_price_ticket', 'gift_ticket', 'staff_credential' ])
                ->with('person:id,callsign,status')
                ->get();


        $documents = [];
        foreach ($rows as $ad) {
            if ($ad->type == 'staff_credential') {
                $ad->access_date = null;
                $ad->access_any_time = false;
            }

            $ad->status = 'banked';
            $ad->addComment('marked as banked via maintenance function', $user);
            $this->saveAccessDocument($ad, $documents);
        }

        // The below code is kind of a hack and probably doesn't
        // really belong in this function

        $rows = AccessDocument::where('status', 'banked')
                ->where('type', 'staff_credential')
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

        return response()->json([ 'access_documents' => $documents ]);
    }

    /*
     * Expire old access documents.
     */

    public function expireAccessDocuments()
    {
        $this->authorize('expireAccessDocuments', [ AccessDocument::class ]);

        $user = $this->user->callsign;

        $rows = AccessDocument::whereIn('status', [ 'banked', 'claimed', 'qualified' ])
                ->whereRaw('expiry_date < NOW()')
                ->with('person:id,callsign,status,email')
                ->get();

        $documents = [];
        foreach ($rows as $ad) {
            $ad->status = 'expired';
            $ad->addComment('marked as expired via maintenance function', $user);
            $this->saveAccessDocument($ad, $documents, true);
        }

        usort($documents, function ($a, $b) {
            return strcasecmp($a['person']['callsign'], $b['person']['callsign']);
        });

        return response()->json([ 'access_documents' => $documents ]);
    }

    /**
     * Bump all banked and qualified tickets' expiration by 1 year.
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */

    public function bumpExpiration()
    {
        $this->authorize('bumpExpiration', AccessDocument::class);
        $params = request()->validate([
            'reason' => 'sometimes|string'
        ]);

        $reason = $params['reason'] ?? null;

        $callsign = $this->user->callsign;

        $rows = AccessDocument::whereIn('status', [ 'banked', 'qualified'])
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

        return response()->json([ 'count' => $rows->count() ]);
    }

    /*
     * Save the access document, log the changes, and build a response.
     */

    private function saveAccessDocument($ad, & $documents, $includeEmail=false)
    {
        $changes = $ad->getChangedValues();
        $ad->save();
        AccessDocumentChanges::log($ad, $this->user->id, $changes);

        $person = $ad->person;
        $result = [
              'id'          => $ad->id,
              'type'        => $ad->type,
              'status'      => $ad->status,
              'source_year' => $ad->source_year,
              'person' => [
                  'id'       => $ad->person_id,
                  'callsign' => $person ? $person->callsign : 'Person #'.$ad->person_id,
                  'status'   => $person ? $person->status : 'unknown',
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

    private function grantAccessDocumentToPeople($people, $type, $accessDate, $year, $status='qualified')
    {
        $user = $this->user->callsign;
        $userId = $this->user->id;

        foreach ($people as $person) {
            $ad = new AccessDocument([
                'person_id'   => $person->id,
                'type'        => $type,
                'status'      => $status,
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

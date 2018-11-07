<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AccessDocument;
use App\Models\AccessDocumentChanges;
use App\Http\Controllers\ApiController;

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

        return $this->success(AccessDocument::findForQuery($query));
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

        if (!$person->save()) {
            return $this->restError($accessDocument);
        }

        return $this->success($accessDocument);
    }

    /*
     * update a specific resource.
     */
    public function update(Request $request, AccessDocument $accessDocument)
    {
        $this->authorize('update', $accessDocument);
        $this->fromRest($accessDocument);

        $changes = $accessDocument->getDirty();

        if (!$this->save()) {
            return $this->restError($accessDocument);
        }

        AccessDocumentChanges::log($accessDocument, $this->user->id, $changes);

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
        //
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
            [ 'status' => 'string' ]
        );

        $status = $request['status'];
        if (!in_array($status, [ 'banked', 'claimed', 'qualified' ])) {
            throw new \InvalidArgumentException('Unknown status action');
        }

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
                    throw new \InvalidArgumentException('Document is not a work access or vehicle pass');
                }

                if ($adStatus != 'claimed') {
                    throw new \InvalidArgumentException('Document is not claimed.');
                }
                break;
        }

        $attrs['status'] = $status;

        $accessDocument->update($attrs);
        AccessDocumentChanges::log($accessDocument, $this->user->id, [ 'status' => $status]);

        $attrs['id'] = $accessDocument->id;

        $this->log('access-document-staus', 'Updated status', $attrs, $accessDocument->person_id);
        return $this->success();
    }

    /*
     * Grab the SO WAP list
     */

    public function retrieveSOWAP(Request $request)
    {
        $params = request()->validate([
            'person_id'    => 'required|integer',
            'year'         => 'required|integer',
        ]);

        $personId = $params['person_id'];
        //$this->authorize('update', [AccessDocument::class, $personId ]);

        return response()->json([ 'names' => $this->buildSOWAPList($personId, $params['year']) ]);
    }

     /*
      * Update the SO WAP list
      */

    public function storeSOWAP(Request $request)
    {
        $params = request()->validate([
            'person_id' => 'required|numeric',
            'year'      => 'required|integer',
            'names'     => 'array|max:10',
            'names.*.name'  => 'sometimes',
            'names.*.id'    => 'required|string'
        ]);


        $person = $this->findPerson($params['person_id']);
        $personId = $person->id;
        $year = $params['year'];

        $this->authorize('storeSOSWAP', [ AccessDocument::class, $personId]);

        $maxSO = config('clubhouse.TAS_WAPSOMax');

        $documents = [];

        foreach ($params['names'] as $row) {
            $soName = $row['name'];
            $soId = $row['id'];

            if ($soId == 'new') {
                // New SO pass is being asked for
                if (empty($soName)) {
                    throw new \InvalidArgumentException('New SO WAP pass requested but no name given.');
                }

                // Make sure the max. has not been hit already
                if (AccessDocument::SOWAPCount($personId, $year) >= $maxSO) {
                    throw new \InvalidArgumentException('New pass would exceed the limit of '.$maxSO.' allowed SO WAP passes.');
                }

                // Looks good, create it
                $accessDocument = AccessDocument::createSOWAP($personId, $year, $soName);
                $documents[] = [ 'id' => $accessDocument->id, 'name' => $soName ];
            } else {
                // Find the existing record
                $wap = AccessDocument::findForPerson($personId, $soId);

                if (empty($soName)) {
                    // Cancel the record
                    $wap->status = 'cancelled';
                } else {
                    // update the name, and claim the pass
                    $wap->status = 'claimed';
                    $wap->name = $soName;
                }

                $dirty = $wap->getDirty();
                if (!empty($dirty)) {
                    $wap->save();
                    $dirty['id'] = $wap->id;
                    $documents[] = $dirty;
                }
            }
        }

        if (!empty($documents)) {
            $this->log('access-document-wap-so', 'Updated list', $documents, $personId);
        }

        // Send back the updated list
        return response()->json([ 'names' => $this->buildSOWAPList($personId) ]);
    }

    public function ticketingInfo()
    {
        return response()->json([
            'ticketing_info' => [
                'is_enabled'             => config('clubhouse.TicketsAndStuffEnable'),
                'is_enabled_for_pnv'     => config('clubhouse.TicketsAndStuffEnablePNV'),
                'ticketing_status'       => config('clubhouse.TAS_Tickets'),
                'vp_status'              => config('clubhouse.TAS_VP'),
                'wap_status'             => config('clubhouse.TAS_WAP'),
                'wap_so_status'          => config('clubhouse.TAS_WAPSO'),
                'wap_so_max'             => config('clubhouse.TAS_WAPSOMax'),
                'box_office_open_date'   => config('clubhouse.TAS_BoxOfficeOpenDate'),
                'wap_default_date'       => config('clubhouse.TAS_DefaultWAPDate'),
                'wap_alpha_default_date' => config('clubhouse.TAS_DefaultAlphaWAPDate'),
                'wap_so_default_date'    => config('clubhouse.TAS_DefaultSOWAPDate'),

                'ticketfly_email'        => 'memberservices@ticketfly.com',
                'ranger_ticketing_email' => config('clubhouse.TAS_Email'),

                'faqs'                   => [
                    'ticketing'          => config('clubhouse.TAS_Ticket_FAQ'),
                    'wap'                => config('clubhouse.TAS_WAP_FAQ'),
                    'vp'                 => config('clubhouse.TAS_VP_FAQ'),
                    'alpha'              => config('clubhouse.TAS_Alpha_FAQ'),
                ]
            ]
        ]);
    }

    private function buildSOWAPList($personId, $year) {
        $rows = AccessDocument::findSOWAPsForPerson($personId, $year);

        $results = [];
        if (!empty($rows)) {
            foreach ($rows as $row) {
                if ($row['access_any_time']) {
                    $date = 'Any Time';
                } else {
                    $date = $row['access_date'] ?$row['access_date']->toDateString() : 'null';
                }
                $results[] = [
                    'id'              => $row['id'],
                    'status'          => $row['status'],
                    'name'            => $row['name'],
                    'access_date'     => $date,
                ];
            }
        }

        return $results;
    }
}

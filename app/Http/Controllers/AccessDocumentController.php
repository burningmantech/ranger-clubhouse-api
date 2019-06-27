<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;

use App\Models\AccessDocument;
use App\Models\AccessDocumentChanges;
use App\Models\Role;

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
     * Retrieve all current/active access documents by person
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

        AccessDocument::whereIn('id', $params['ids'])
            ->where('status', 'claimed')
            ->update([ 'status' => 'submitted' ]);

        return $this->success();
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
                    throw new \InvalidArgumentException('Document is not a work access or vehicle pass');
                }

                if ($adStatus != 'claimed') {
                    throw new \InvalidArgumentException('Document is not claimed.');
                }
                break;

            default:
                throw new \InvalidArgumentException('Unknown status action');
                break;
        }

        $attrs['status'] = $status;

        $accessDocument->update($attrs);
        AccessDocumentChanges::log($accessDocument, $this->user->id, [ 'status' => $status]);

        $attrs['id'] = $accessDocument->id;
        $this->log('access-document-status', 'Updated status', $attrs, $accessDocument->person_id);

        return $this->success();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AccessDocumentDelivery;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;

class AccessDocumentDeliveryController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $query = request()->validate([
            'year'      => 'sometimes|digits:4',
            'person_id' => 'sometimes|numeric',
            ]);

        $personId = empty($query['person_id']) ? null : $query['person_id'];

        $this->authorize('index', [ AccessDocumentDelivery::class, $personId ]);

        $rows = AccessDocumentDelivery::findForQuery($query);

        if ($rows->isEmpty()) {
            return $this->restError('No records found', 404);
        }

        return $this->success($rows);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorize('create', AccessDocumentDelivery::class);

        $add = new AccessDocumentDelivery;
        $this->fromRest($add);

        if ($add->save()) {
            return $this->success($add);
        }

        return $this->restError($add);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\AccessDocumentDelivery  $accessDocumentDelivery
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, AccessDocumentDelivery $add)
    {
        $this->authorize('update', $add);
        $this->fromRest($add);

        if ($add->save()) {
            return $this->success($add);
        }

        return $this->restError($add);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\AccessDocumentDelivery  $accessDocumentDelivery
     * @return \Illuminate\Http\Response
     */
    public function destroy(AccessDocumentDelivery $add)
    {
        $this->authorize('delete', $add);
    }
}

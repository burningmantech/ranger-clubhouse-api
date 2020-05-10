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

        return $this->success($rows, null, 'access_document_delivery');
    }

    /**
     * Show a single Access Document Delivery
     *
     * @param AccessDocumentDelivery $add
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show(AccessDocumentDelivery $add)
   {
       $this->authorize('show', $add);

       return $this->success($add);
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
     * @param  \App\Models\AccessDocumentDelivery  $accessDocumentDelivery
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
     * @param  \App\Models\AccessDocumentDelivery  $accessDocumentDelivery
     * @return \Illuminate\Http\Response
     */
    public function destroy(AccessDocumentDelivery $add)
    {
        $this->authorize('delete', $add);
    }
}

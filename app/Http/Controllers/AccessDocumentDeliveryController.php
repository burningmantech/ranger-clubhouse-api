<?php

namespace App\Http\Controllers;

use App\Models\AccessDocumentDelivery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use Illuminate\Http\Response;

class AccessDocumentDeliveryController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function index()
    {
        $query = request()->validate([
            'year' => 'sometimes|digits:4',
            'person_id' => 'sometimes|numeric',
        ]);

        $personId = empty($query['person_id']) ? null : $query['person_id'];

        $this->authorize('index', [AccessDocumentDelivery::class, $personId]);

        $rows = AccessDocumentDelivery::findForQuery($query);

        return $this->success($rows, null, 'access_document_delivery');
    }

    /**
     * Show a single Access Document Delivery
     *
     * @param AccessDocumentDelivery $add
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function show(AccessDocumentDelivery $add)
    {
        $this->authorize('show', $add);

        return $this->success($add);
    }

    /**
     * Create a new Access Document Delivery OR allow an update to an existing one.
     * (slight bit of a hack to support the frontend)
     *
     * @return JsonResponse
     */
    public function store()
    {
        $params = request()->validate([
            'access_document_delivery.person_id' => 'required|integer',
            'access_document_delivery.year' => 'required|integer'
        ]);
        $p = $params['access_document_delivery'];
        $personId = $p['person_id'];
        $year = $p['year'];

        $this->authorize('create', [AccessDocumentDelivery::class, $personId]);

        $add = AccessDocumentDelivery::findOrNewForPersonYear($personId, $year);
        $this->fromRest($add);

        if ($add->save()) {
            return $this->success($add);
        }

        return $this->restError($add);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param AccessDocumentDelivery $accessDocumentDelivery
     * @return JsonResponse
     */
    public function update(AccessDocumentDelivery $add)
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
     * @param AccessDocumentDelivery $accessDocumentDelivery
     * @return Response
     */
    public function destroy(AccessDocumentDelivery $add)
    {
        $this->authorize('delete', $add);
    }
}

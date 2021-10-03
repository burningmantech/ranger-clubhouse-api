<?php

namespace App\Http\Controllers;

use App\Models\AssetPerson;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class AssetPersonController extends ApiController
{
    /**
     * Find an asset set based on person and/or year.
     *
     * @return JsonResponse
     */

    public function index(): JsonResponse
    {
        $params = request()->validate([
            'person_id' => 'sometimes|integer',
            'year' => 'sometimes|integer',
        ]);

        $rows = AssetPerson::findForQuery($params);

        return $this->success($rows, null, 'asset_person');
    }

    /**
     * Create an asset person
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', [AssetPerson::class]);
        $asset_person = new AssetPerson;
        $this->fromRest($asset_person);

        if (!$asset_person->save()) {
            return $this->restError($asset_person);
        }

        return $this->success($asset_person);
    }

    /**
     * Retrieve an asset person
     *
     * @param AssetPerson $asset_person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(AssetPerson $asset_person): JsonResponse
    {
        $this->authorize('show', $asset_person);
        $asset_person->loadRelationships();

        return $this->success($asset_person);
    }

    /**
     * Update an AssetPerson record
     *
     * @param AssetPerson $asset_person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function update(AssetPerson $asset_person): JsonResponse
    {
        $this->authorize('update', $asset_person);
        $this->fromRest($asset_person);
        if (!$asset_person->save()) {
            return $this->restError($asset_person);
        }

        $asset_person->loadRelationships();
        return $this->success($asset_person);
    }

    /**
     * Delete an asset person record
     *
     * @param AssetPerson $asset_person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(AssetPerson $asset_person): JsonResponse
    {
        $this->authorize('destroy', $asset_person);
        $asset_person->delete();

        return $this->restDeleteSuccess();
    }

    /**
     * Radio checkout report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function radioCheckoutReport(): JsonResponse
    {
        $this->authorize('radioCheckoutReport', AssetPerson::class);

        $params = request()->validate([
            'include_qualified' => 'sometimes|boolean',
            'event_summary' => 'sometimes|boolean',
            'hour_limit' => 'sometimes|integer',
            'year' => 'sometimes|integer',
        ]);

        return response()->json(['radios' => RadioCheckoutReport::execute($params)]);
    }
}

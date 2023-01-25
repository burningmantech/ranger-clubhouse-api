<?php

namespace App\Http\Controllers;

use App\Lib\Reports\SwagDistributionReport;
use App\Models\PersonSwag;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class PersonSwagController extends ApiController
{
    /**
     * Show the swags for folks
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $params = request()->validate([
            'person_id' => 'sometimes|integer',
            'swag_id' => 'sometimes|integer',
            'year_issued' => 'sometimes|integer',
            'include_person' => 'sometimes|boolean',
        ]);

        $this->authorize('index', [PersonSwag::class, $params['person_id'] ?? null]);

        return $this->success(PersonSwag::findForQuery($params), null, 'person_swag');
    }

    /**
     * Show a single swag
     *
     * @param PersonSwag $personSwag
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(PersonSwag $personSwag): JsonResponse
    {
        $this->authorize('show', $personSwag);
        $personSwag->loadRelationships();
        return $this->success($personSwag);
    }

    /**
     * Create a person swag
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', PersonSwag::class);

        $personSwag = new PersonSwag();
        $this->fromRest($personSwag);

        if ($personSwag->save()) {
            $personSwag->loadRelationships();
            return $this->success($personSwag);
        }

        return $this->restError($personSwag);
    }

    /**
     * Update a person swag
     *
     * @param PersonSwag $personSwag
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function update(PersonSwag $personSwag): JsonResponse
    {
        $this->authorize('update', $personSwag);
        $this->fromRest($personSwag);

        if ($personSwag->save()) {
            $personSwag->loadRelationships();
            return $this->success($personSwag);
        }

        return $this->restError($personSwag);
    }

    /**
     * Delete a person swag
     *
     * @param PersonSwag $personSwag
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(PersonSwag $personSwag): JsonResponse
    {
        $this->authorize('destroy', $personSwag);

        $personSwag->delete();

        return $this->restDeleteSuccess();
    }

    /**
     * Run the swag distribution report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function distribution() : JsonResponse
    {
        $this->authorize('distribution', PersonSwag::class);

        return response()->json([ 'people' => SwagDistributionReport::execute($this->getYear())]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Lib\Reports\PotentialSwagReport;
use App\Models\PersonSwag;
use App\Models\Swag;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class SwagController extends ApiController
{
    /**
     * Display all the swags
     * @return JsonResponse
     */

    public function index(): JsonResponse
    {
        $query = request()->validate([
            'type' => 'sometimes|string',
            'category' => 'sometimes|string',
            'active' => 'sometimes|boolean',
        ]);

        return $this->success(Swag::findForQuery($query), null, 'swag');
    }

    /**
     * Create a swag
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', Swag::class);

        $swag = new Swag;
        $this->fromRest($swag);

        if ($swag->save()) {
            return $this->success($swag);
        }

        return $this->restError($swag);
    }

    /**
     * Display a swag
     *
     * @param Swag $swag
     * @return JsonResponse
     */

    public function show(Swag $swag): JsonResponse
    {
        return $this->success($swag);
    }

    /**
     * Update a swag
     *
     * @param Swag $swag
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function update(Swag $swag): JsonResponse
    {
        $this->authorize('update', Swag::class);
        $this->fromRest($swag);

        if ($swag->save()) {
            return $this->success($swag);
        }

        return $this->restError($swag);
    }

    /**
     * Delete a swag
     *
     * @param Swag $swag
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(Swag $swag): JsonResponse
    {
        $this->authorize('delete', Swag::class);
        $swag->delete();
        PersonSwag::where('swag_id', $swag->id)->delete();
        return $this->restDeleteSuccess();
    }

    /**
     * Find the shirt appreciations. Used to present shirt choices to the user.
     *
     * @return JsonResponse
     */

    public function shirts(): JsonResponse
    {
        return response()->json(['shirts' => Swag::retrieveShirts()]);
    }

    /**
     * Report on who might be eligible for swag
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function potentialSwagReport(): JsonResponse
    {
        $this->authorize('potentialSwagReport', Swag::class);

        return response()->json(PotentialSwagReport::execute());
    }
}

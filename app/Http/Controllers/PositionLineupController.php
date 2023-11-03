<?php

namespace App\Http\Controllers;

use App\Models\PositionLineup;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PositionLineupController extends ApiController
{
    /**
     * Show a list of position lineups
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $this->authorize('index', PositionLineup::class);
        $params = request()->validate([
            'position_id' => 'sometimes|integer'
        ]);

        return $this->success(PositionLineup::findForQuery($params), null, 'position_lineup');
    }

    /**
     * Create a position lineup
     *
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws ValidationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', PositionLineup::class);

        $positionLineup = new PositionLineup();
        $this->fromRest($positionLineup);

        if (!$positionLineup->save()) {
            return $this->restError($positionLineup);
        }

        $positionLineup->loadPositionIds();
        return $this->success($positionLineup);
    }

    /**
     * Show a position lineup
     * @param PositionLineup $positionLineup
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(PositionLineup $positionLineup): JsonResponse
    {
        $this->authorize('show', $positionLineup);
        $positionLineup->loadPositionIds();
        return $this->success($positionLineup);
    }

    /**
     * Update a position lineup
     *
     * @param PositionLineup $positionLineup
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws ValidationException
     */

    public function update(PositionLineup $positionLineup): JsonResponse
    {
        $this->authorize('update', PositionLineup::class);
        $this->fromRest($positionLineup);

        if (!$positionLineup->save()) {
            return $this->restError($positionLineup);
        }

        $positionLineup->loadPositionIds();
        return $this->success($positionLineup);
    }

    /**
     * Delete the position lineup
     *
     * @param PositionLineup $positionLineup
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(PositionLineup $positionLineup): JsonResponse
    {
        $this->authorize('destroy', $positionLineup);
        $positionLineup->delete();
        return $this->restDeleteSuccess();
    }
}

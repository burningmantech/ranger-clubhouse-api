<?php

namespace App\Http\Controllers;

use App\Models\PersonPositionLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class PersonPositionLogController extends ApiController
{
    /**
     * Display a position log listing.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $params = request()->validate([
            'person_id' => 'sometimes|integer|exists:person,id',
            'position_id' => 'sometimes|integer|exists:position,id',
        ]);

        $this->authorize('index', [PersonPositionLog::class, $params['person_id'] ?? null]);

        return $this->success(PersonPositionLog::findForQuery($params), null, 'person_position_log');
    }

    /**
     * Store a position log  record
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', PersonPositionLog::class);

        $PersonPositionLog = new PersonPositionLog;
        $this->fromRest($PersonPositionLog);

        if ($PersonPositionLog->save()) {
            $PersonPositionLog->loadRelationships();
            return $this->success($PersonPositionLog);
        }

        return $this->restError($PersonPositionLog);
    }

    /**
     * Display the position log
     *
     * @param PersonPositionLog $PersonPositionLog
     * @return JsonResponse
     */

    public function show(PersonPositionLog $PersonPositionLog): JsonResponse
    {
        $PersonPositionLog->loadRelationships();
        return $this->success($PersonPositionLog);
    }

    /**
     * Update the position log.
     *
     * @param PersonPositionLog $PersonPositionLog
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function update(PersonPositionLog $PersonPositionLog): JsonResponse
    {
        $this->authorize('update', $PersonPositionLog);
        $this->fromRest($PersonPositionLog);

        if ($PersonPositionLog->save()) {
            $PersonPositionLog->loadRelationships();
            return $this->success($PersonPositionLog);
        }

        return $this->restError($PersonPositionLog);
    }

    /**
     * Remove a position log
     *
     * @param PersonPositionLog $PersonPositionLog
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(PersonPositionLog $PersonPositionLog): JsonResponse
    {
        $this->authorize('delete', $PersonPositionLog);
        $PersonPositionLog->delete();
        return $this->restDeleteSuccess();
    }
}

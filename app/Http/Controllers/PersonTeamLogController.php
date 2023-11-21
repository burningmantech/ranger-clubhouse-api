<?php

namespace App\Http\Controllers;

use App\Models\PersonTeamLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PersonTeamLogController extends ApiController
{
    /**
     * Display a team history listing.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $params = request()->validate([
            'person_id' => 'sometimes|integer|exists:person,id',
            'team_id' => 'sometimes|integer|exists:team,id'
        ]);

        $this->authorize('index', [PersonTeamLog::class, $params['person_id'] ?? null]);

        return $this->success(PersonTeamLog::findForQuery($params), null, 'person_team_log');
    }

    /**
     * Store a team history record
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', PersonTeamLog::class);

        $personTeamLog = new PersonTeamLog;
        $this->fromRest($personTeamLog);

        if ($personTeamLog->save()) {
            $personTeamLog->loadRelationships();
            return $this->success($personTeamLog);
        }

        return $this->restError($personTeamLog);
    }

    /**
     * Display the person team log.
     *
     * @param PersonTeamLog $personTeamLog
     * @return JsonResponse
     */

    public function show(PersonTeamLog $personTeamLog): JsonResponse
    {
        $personTeamLog->loadRelationships();
        return $this->success($personTeamLog);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param PersonTeamLog $personTeamLog
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function update(PersonTeamLog $personTeamLog): JsonResponse
    {
        $this->authorize('update', $personTeamLog);
        $this->fromRest($personTeamLog);

        if ($personTeamLog->save()) {
            $personTeamLog->loadRelationships();
            return $this->success($personTeamLog);
        }

        return $this->restError($personTeamLog);
    }

    /**
     * Remove the team log.
     *
     * @param PersonTeamLog $personTeamLog
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(PersonTeamLog $personTeamLog): JsonResponse
    {
        $this->authorize('delete', $personTeamLog);
        $personTeamLog->delete();
        return $this->restDeleteSuccess();
    }
}

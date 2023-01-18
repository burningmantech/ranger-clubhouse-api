<?php

namespace App\Http\Controllers;

use App\Lib\Reports\PeopleByTeamsReport;
use App\Models\PersonTeam;
use App\Models\PersonTeamLog;
use App\Models\Position;
use App\Models\Team;
use App\Models\TeamRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TeamController extends ApiController
{
    /**
     * Show teams.
     *
     * @return JsonResponse
     */

    public function index(): JsonResponse
    {
        $params = request()->validate([
            'can_manage' => 'sometimes|bool',
            'include_roles' => 'sometimes|bool',
            'include_managers' => 'sometimes|bool',
        ]);

        return $this->success(Team::findForQuery($params, $this->user->id, $this->user->isAdmin()), null, 'team');
    }

    /**
     * Create a new team.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', Team::class);

        $team = new Team;
        $this->fromRest($team);

        if ($team->save()) {
            $team->loadRoles();
            return $this->success($team);
        }

        return $this->restError($team);
    }

    /**
     * Display a team.
     *
     * @param Team $team
     * @return JsonResponse
     */

    public function show(Team $team): JsonResponse
    {
        $team->loadRoles();
        return $this->success($team);
    }

    /**
     * Update a team.
     *
     * @param Team $team
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function update(Team $team): JsonResponse
    {
        $this->authorize('update', Team::class);
        $this->fromRest($team);

        if ($team->save()) {
            $team->loadRoles();
            Cache::forget();
            return $this->success($team);
        }

        return $this->restError($team);
    }

    /**
     * Remove the team.
     *
     * @param Team $team
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(Team $team): JsonResponse
    {
        $this->authorize('delete', Team::class);
        $team->delete();
        Position::where('team_id', $team->id)->update(['team_id' => null]);
        PersonTeam::where('team_id', $team->id)->delete();
        PersonTeamLog::where('team_id', $team->id)->delete();
        TeamRole::where('team_id', $team->id)->delete();
        Cache::forget();
        return $this->restDeleteSuccess();
    }

    /**
     * People By Teams Report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function peopleByTeamsReport(): JsonResponse
    {
        $this->authorize('peopleByTeamsReport', Team::class);

        return response()->json(['teams' => PeopleByTeamsReport::execute()]);
    }
}

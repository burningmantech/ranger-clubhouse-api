<?php

namespace App\Http\Controllers;

use App\Lib\BulkTeamGrantRevoke;
use App\Lib\Reports\PeopleByTeamsReport;
use App\Lib\Reports\TeamMembershipReport;
use App\Models\Team;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

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

    /**
     * Grant or revoke a team membership for a list of callsigns
     *
     * @param Team $team
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function bulkGrantRevoke(Team $team): JsonResponse
    {
        $this->authorize('bulkGrantRevoke', $team);
        $params = request()->validate([
            'callsigns' => 'string|required',
            'grant' => 'boolean|required',
            'commit' => 'boolean|sometimes',
        ]);

        return response()->json([
            'people' => BulkTeamGrantRevoke::execute(
                $params['callsigns'],
                $team->id,
                $params['grant'],
                $params['commit'] ?? false
            )
        ]);
    }

    /**
     * Report on a team's membership
     *
     * @param Team $team
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function membership(Team $team) : JsonResponse
    {
        $this->authorize('membership', $team);

        return response()->json(TeamMembershipReport::execute($team));
    }
}

<?php

namespace App\Http\Controllers;

use App\Lib\BulkTeamGrantRevoke;
use App\Lib\Reports\DirectoryReport;
use App\Lib\Reports\PeopleByTeamsReport;
use App\Lib\Reports\TeamMembershipReport;
use App\Models\Person;
use App\Models\PersonTeam;
use App\Models\Role;
use App\Models\Team;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

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
            'can_edit_resources' => 'sometimes|bool',
            'include_roles' => 'sometimes|bool',
            'include_managers' => 'sometimes|bool',
            'awards_eligible' => 'sometimes|bool',
        ]);

        return $this->success(Team::findForQuery($params, $this->user->id, $this->user->isAdmin()), null, 'team');
    }

    /**
     * Create a new team.
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', Team::class);

        $team = new Team;
        $this->fromRest($team);

        return $this->saveAndRespond($team);
    }

    /**
     * Display a team.
     *
     * @param Team $team
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(Team $team): JsonResponse
    {
        $this->authorize('view', $team);

        $team->load('team_roles');
        $team->appendRoleIds();

        return $this->success($team);
    }

    /**
     * Update a team.
     *
     * @param Team $team
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function update(Team $team): JsonResponse
    {
        $this->authorize('update', $team);
        $this->fromRest($team);

        return $this->saveAndRespond($team);
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
        $this->authorize('delete', $team);
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

        if ($team->team_roles()->whereIn('role_id', [Role::ADMIN, Role::TECH_NINJA])->exists()) {
            throw new AuthorizationException("Cannot grant or revoke membership for a team that has the Admin or Tech Ninja Role associated. Use the appropriate administrative interface instead.");
        }

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

    public function membership(Team $team): JsonResponse
    {
        $this->authorize('membership', $team);

        return response()->json(TeamMembershipReport::execute($team));
    }

    /**
     * Retrieve what Cadres and Delegations the person is a member of.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function opsMembership(Person $person): JsonResponse
    {
        $this->authorize('opsMembership', $person);
        return response()->json(PersonTeam::retrieveOpsMembershipForPerson($person->id));
    }

    /**
     * Provide a directory of known Cadres & Delegations
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function directory(): JsonResponse
    {
        $this->authorize('directory', Team::class);

        return response()->json(['teams' => DirectoryReport::execute()]);
    }

    /**
     * Persist a team, project its role_ids for the response, and shape the REST result.
     *
     * Extracted from store() and update() to remove the duplicated save / append / error block.
     *
     * @param Team $team
     * @return JsonResponse
     */

    private function saveAndRespond(Team $team): JsonResponse
    {
        if (!$team->save()) {
            return $this->restError($team);
        }

        $team->load('team_roles');
        $team->appendRoleIds();

        return $this->success($team);
    }
}

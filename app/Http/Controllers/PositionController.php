<?php

namespace App\Http\Controllers;

use App\Lib\BulkPositionGrantRevoke;
use App\Lib\ClubhouseCache;
use App\Lib\Reports\PeopleByPositionReport;
use App\Lib\Reports\PeopleByTeamsReport;
use App\Lib\Reports\SandmanQualificationReport;
use App\Models\PersonPosition;
use App\Models\Position;
use App\Models\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PositionController extends ApiController
{
    /**
     * Show a list of positions
     *
     * @return JsonResponse
     */

    public function index(): JsonResponse
    {
        $params = request()->validate([
            'type' => 'sometimes|string',
            'can_manage' => 'sometimes|boolean',
            'cruise_direction' => 'sometimes|boolean',
            'include_roles' => 'sometimes|boolean',
            'has_paycode' => 'sometimes|boolean',
            'active' => 'sometimes|boolean',
            'mentee' => 'sometimes|boolean',
            'mentor' => 'sometimes|boolean',
        ]);

        return $this->success(Position::findForQuery($params, $this->user->isAdmin()), null, 'position');
    }

    /**
     * Create a position
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', Position::class);

        $position = new Position;
        $this->fromRest($position);

        if (!$position->save()) {
            return $this->restError($position);
        }

        ClubhouseCache::flush();
        return $this->success($position);
    }

    /**
     * Show a position
     *
     * @param Position $position
     * @return JsonResponse
     */

    public function show(Position $position): JsonResponse
    {
        $position->loadRoles();
        return $this->success($position);
    }

    /**
     * Update a position
     *
     * @param Position $position
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function update(Position $position): JsonResponse
    {
        $this->authorize('update', $position);
        $this->fromRest($position);

        if ($position->save()) {
            $position->loadRoles();
            return $this->success($position);
        }

        // Positions affect role permissions, dump the cache.
        ClubhouseCache::flush();
        return $this->restError($position);
    }

    /**
     * Delete the position controller
     *
     * @param Position $position
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(Position $position): JsonResponse
    {
        $this->authorize('delete', $position);
        $position->delete();
        return $this->restDeleteSuccess();
    }

    /**w
     * Report on all positions who has been granted said position
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function peopleByPosition(): JsonResponse
    {
        $this->authorize('peopleByPosition', Position::class);
        $params = request()->validate([
            'onPlaya' => 'sometimes|boolean'
        ]);

        $onPlaya = $params['onPlaya'] ?? false;

        return response()->json(PeopleByPositionReport::execute($onPlaya));
    }

    /**
     * Sandman Qualification Report
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function sandmanQualifiedReport(): JsonResponse
    {
        $this->authorize('sandmanQualified', Position::class);
        return response()->json(SandmanQualificationReport::execute());
    }

    /**
     * People By Teams Report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function peopleByTeamsReport(): JsonResponse
    {
        $this->authorize('peopleByTeamsReport', Position::class);
        return response()->json(PeopleByTeamsReport::execute());
    }

    /**
     * Grant or revoke a position for a list of callsigns
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function bulkGrantRevoke(Position $position): JsonResponse
    {
        $this->authorize('bulkGrantRevoke', $position);
        $params = request()->validate([
            'callsigns' => 'string|required',
            'grant' => 'boolean|required',
            'commit' => 'boolean|sometimes',
        ]);

        if ($params['grant'] && $position->position_roles->first(fn($pr) => $pr->role_id == Role::ADMIN || $pr->role_id == Role::TECH_NINJA)) {
            throw new AuthorizationException("The position has either the Admin or Tech Ninja Role associated and cannot be granted through this interface.");
        }

        return response()->json([
            'people' => BulkPositionGrantRevoke::execute(
                $params['callsigns'],
                $position->id,
                $params['grant'],
                $params['commit'] ?? false
            )
        ]);
    }

    /**
     * Retrieve folks granted a particular position
     *
     * @throws AuthorizationException
     */

    public function grants(Position $position): JsonResponse
    {
        $this->authorize('grants', Position::class);

        return response()->json(['people' => PersonPosition::retrieveGrants($position->id)]);
    }
}

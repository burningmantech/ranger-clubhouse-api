<?php

namespace App\Http\Controllers;

use App\Lib\BulkPositionGrantRevoke;
use App\Lib\ClubhouseCache;
use App\Lib\Reports\PeopleByPositionReport;
use App\Lib\Reports\PeopleByTeamsReport;
use App\Lib\Reports\SandmanQualificationReport;
use App\Models\Person;
use App\Models\Position;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PositionController extends ApiController
{
    /**
     * Show a lsit of positions
     *
     * @return JsonResponse
     */

    public function index(): JsonResponse
    {
        $params = request()->validate([
            'type' => 'sometimes|string',
            'can_manage' => 'sometimes|boolean',
            'include_roles' => 'sometimes|boolean',
        ]);

        return $this->success(Position::findForQuery($params), null, 'position');
    }

    /**
     * Create a position
     *
     * @return JsonResponse
     * @throws AuthorizationException
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
     * @throws AuthorizationException
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
        DB::table('position_role')->where('position_id', $position->id)->delete();
        DB::table('person_position')->where('position_id', $position->id)->delete();
        ClubhouseCache::flush();
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
        $this->authorize('peopleByPosition', Person::class);
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

    public function bulkGrantRevoke(): JsonResponse
    {
        $this->authorize('bulkGrantRevoke', Position::class);
        $params = request()->validate([
            'callsigns' => 'string|required',
            'position_id' => 'integer|required|exists:position,id',
            'grant' => 'boolean|required',
            'commit' => 'boolean|sometimes',
        ]);

        return response()->json([
            'people' => BulkPositionGrantRevoke::execute(
                $params['callsigns'],
                $params['position_id'],
                $params['grant'],
                $params['commit'] ?? false
            )
        ]);
    }
}

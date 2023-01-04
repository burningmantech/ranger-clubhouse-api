<?php

namespace App\Http\Controllers;

use App\Lib\BulkPositionGrantRevoke;
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
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */

    public function index()
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

        return $this->success($position);
    }

    /**
     * Display the specified resource.
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
     * Update the specified resource in storage.
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

        return $this->restError($position);
    }

    /**
     * Remove the specified resource from storage.
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
        return $this->restDeleteSuccess();
    }

    /**
     * Report on all positions who has been granted said position
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function peopleByPosition(): JsonResponse
    {
        $this->authorize('index', Person::class);
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

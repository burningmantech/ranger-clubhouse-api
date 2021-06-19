<?php

namespace App\Http\Controllers;

use App\Lib\Reports\PeopleByPositionReport;
use App\Lib\Reports\SandmanQualificationReport;
use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\Position;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'type' => 'sometimes|string'
        ]);

        return $this->success(Position::findForQuery($params), null);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $this->authorize('store', Position::class);

        $position = new Position;
        $this->fromRest($position);

        if ($position->save()) {
            return $this->success($position);
        }

        return $this->restError($position);
    }

    /**
     * Display the specified resource.
     *
     * @param Position $position
     * @return JsonResponse
     */
    public function show(Position $position)
    {
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
        $this->authorize('update', Position::class);
        $this->fromRest($position);

        if ($position->save()) {
            return $this->success($position);
        }

        return $this->restError($position);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Position $position
     * @return JsonResponse
     */
    public function destroy(Position $position)
    {
        $this->authorize('delete', Position::class);
        $position->delete();
        PersonPosition::where('position_id', $position->id)->delete();
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
     */
    public function sandmanQualifiedReport()
    {
        $this->authorize('sandmanQualified', Position::class);
        return response()->json(SandmanQualificationReport::execute());
    }
}

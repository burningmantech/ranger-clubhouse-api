<?php

namespace App\Http\Controllers;

use App\Lib\BulkGrantAward;
use App\Models\Award;
use App\Models\PersonAward;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class AwardController extends ApiController
{
    /**
     * Display all the awards
     * @return JsonResponse
     */

    public function index(): JsonResponse
    {
        return $this->success(Award::findAll(), null, 'award');
    }

    /**
     * Create an award
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', Award::class);

        $award = new Award;
        $this->fromRest($award);

        if ($award->save()) {
            return $this->success($award);
        }

        return $this->restError($award);
    }

    /**
     * Display an award
     *
     * @param Award $award
     * @return JsonResponse
     */

    public function show(Award $award): JsonResponse
    {
        return $this->success($award);
    }

    /**
     * Update an award
     *
     * @param Award $award
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function update(Award $award): JsonResponse
    {
        $this->authorize('update', Award::class);
        $this->fromRest($award);

        if ($award->save()) {
            return $this->success($award);
        }

        return $this->restError($award);
    }

    /**
     * Delete an award
     *
     * @param Award $award
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(Award $award): JsonResponse
    {
        $this->authorize('delete', Award::class);
        $award->delete();
        PersonAward::where('award_id', $award->id)->delete();
        return $this->restDeleteSuccess();
    }

    /**
     * Grant bulk grant a specific award
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function bulkGrantAward() : JsonResponse
    {
        $this->authorize('bulkGrantAward', Award::class);

        $params = request()->validate([
            'commit' => 'sometimes|boolean',
            'award_id' => 'required|integer|exists:award,id',
            'callsigns' => 'required|string'
        ]);

        return response()->json([
            'people' => BulkGrantAward::bulkGrant($params['award_id'], $params['callsigns'], $params['commit'] ?? false)
        ]);
    }

    /**
     * Grant an award based on the service years.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function bulkGrantServiceYearsAward() : JsonResponse
    {
        $this->authorize('bulkGrantServiceYearsAward', Award::class);

        $params = request()->validate([
            'commit' => 'sometimes|boolean',
            'award_id' => 'required|integer|exists:award,id',
            'service_years' => 'required|integer'
        ]);

        return response()->json([
            'people' => BulkGrantAward::grantServiceYearsAward($params['award_id'], $params['service_years'], $params['commit'] ?? false)
        ]);
    }
}

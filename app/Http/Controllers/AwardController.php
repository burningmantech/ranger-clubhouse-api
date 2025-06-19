<?php

namespace App\Http\Controllers;

use App\Lib\Reports\ServiceYearsReport;
use App\Models\Award;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

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
     * @throws AuthorizationException|ValidationException
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
        return $this->restDeleteSuccess();
    }


    /**
     * Service Years report
     *
     * @return JsonResponse
     * throws AuthorizationException
     * @throws AuthorizationException
     */

    public function serviceYearsReport(): JsonResponse
    {
        $this->authorize('serviceYearsReport', [Award::class]);

        return response()->json([
            'serviceYears' => ServiceYearsReport::execute()
        ]);
    }
}

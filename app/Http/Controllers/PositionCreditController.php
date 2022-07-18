<?php

namespace App\Http\Controllers;

use App\Models\PositionCredit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PositionCreditController extends ApiController
{
    /**
     * Show all the position credits for a year
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $params = request()->validate([
            'year' => 'required|integer',
        ]);

        $this->authorize('view', PositionCredit::class);

        return $this->success(PositionCredit::findForYear($params['year']), null, 'position_credit');
    }

    /**
     * Create a new position credit
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', PositionCredit::class);

        $position_credit = new PositionCredit;
        $this->fromRest($position_credit);

        if ($position_credit->save()) {
            $position_credit->loadRelations();
            return $this->success($position_credit);
        }

        return $this->restError($position_credit);
    }

    /**
     * Show a single position credit
     *
     * @param PositionCredit $position_credit
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(PositionCredit $position_credit): JsonResponse
    {
        $this->authorize('view', PositionCredit::class);
        $position_credit->loadRelations();
        return $this->success($position_credit);
    }

    /**
     * Update a position credit
     *
     * @param PositionCredit $position_credit
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function update(PositionCredit $position_credit): JsonResponse
    {
        $this->authorize('update', PositionCredit::class);
        $this->fromRest($position_credit);

        if ($position_credit->save()) {
            $position_credit->loadRelations();
            return $this->success($position_credit);
        }

        return $this->restError($position_credit);
    }

    /**
     * Remove a position credit
     *
     * @param PositionCredit $position_credit
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(PositionCredit $position_credit): JsonResponse
    {
        $this->authorize('delete', PositionCredit::class);
        $position_credit->delete();
        return $this->restDeleteSuccess();
    }

    /**
     * Copy position credits in bulk.  This supports two main use cases:
     * #1: Copy with a date delta, e.g. add 364 days (to align with Labor Day) to last year's credits.
     *     Set deltaDays, deltaHours, deltaMinutes as appropriate.
     * #2: Create credit values for a new position based on another position.  Set newPositionId.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function copy(): JsonResponse
    {
        $this->authorize('store', PositionCredit::class);
        $params = request()->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer',
            'deltaDays' => 'sometimes|integer',
            'deltaHours' => 'sometimes|integer',
            'deltaMinutes' => 'sometimes|integer',
            'newPositionId' => 'sometimes|exists:position,id',
        ]);

        $deltaDays = $params['deltaDays'] ?? 0;
        $deltaHours = $params['deltaHours'] ?? 0;
        $deltaMinutes = $params['deltaMinutes'] ?? 0;
        if ($deltaDays != 0 || $deltaHours != 0 || $deltaMinutes != 0) {
            $delta = "$deltaDays day $deltaHours hour $deltaMinutes minute";
        } else {
            $delta = NULL;
        }
        $position = $params['newPositionId'] ?? NULL;
        if (!$delta && !$position) {
            return $this->restError('Must specify new position or a day/time delta');
        }
        $sourceCredits = PositionCredit::find($params['ids']);
        $results = array();
        DB::transaction(function () use ($sourceCredits, $delta, $position, &$results) {
            // TODO add a unique index on (position_id, start_time, end_time) so it's hard to double-copy
            foreach ($sourceCredits as $source) {
                $target = $source->replicate();
                if ($delta) {
                    $target->start_time = $source->start_time->modify($delta);
                    $target->end_time = $source->end_time->modify($delta);
                }
                if (!empty($position)) {
                    $target->position_id = $position;
                }
                $target->auditReason = 'position credit copy';
                $target->saveOrThrow();
                array_push($results, $target);
            }
        });
        return $this->success($results, null, 'position_credit');
    }
}

<?php

namespace App\Http\Controllers;

use App\Exceptions\UnacceptableConditionException;
use App\Lib\ProvisionMaintenance;
use App\Lib\Reports\ProvisionUnsubmitRecommendationReport;
use App\Models\Person;
use App\Models\Provision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProvisionController extends ApiController
{
    /**
     * Retrieve a provisions list
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $query = request()->validate([
            'year' => 'sometimes|digits:4',
            'person_id' => 'sometimes|numeric',
            'include_person' => 'sometimes|bool',
            'status' => 'sometimes|string',
            'type' => 'sometimes|string',
        ]);

        $this->authorize('index', [Provision::class, $query['person_id'] ?? 0]);

        return $this->success(Provision::findForQuery($query), null, 'provision');
    }

    /**
     * Add a comment to a given set of provisions.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function bulkComment(): JsonResponse
    {
        $this->authorize('bulkComment', Provision::class);
        $params = request()->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'comment' => 'required|string'
        ]);

        $comment = $params['comment'];
        $rows = Provision::whereIntegerInRaw('id', $params['ids'])->get();

        foreach ($rows as $row) {
            $row->addComment($comment, $this->user);
            $row->saveWithoutValidation();
        }

        return response()->json(['status' => 'success']);
    }


    /**
     * Show single Provision
     *
     * @param Provision $provision
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(Provision $provision): JsonResponse
    {
        $this->authorize('index', [Provision::class, $provision->person_id]);
        return $this->success($provision);
    }

    /**
     * Create a provision
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('create', Provision::class);

        $provision = new Provision;
        $this->fromRest($provision);

        if (!$provision->save()) {
            return $this->restError($provision);
        }

        return $this->success($provision);
    }

    /**
     * Update a provision
     *
     * @param Provision $provision
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function update(Provision $provision): JsonResponse
    {
        $this->authorize('update', $provision);
        $this->fromRest($provision);

        if (!$provision->save()) {
            return $this->restError($provision);
        }

        return $this->success($provision);
    }

    /**
     * Delete the provision
     *
     * @param Provision $provision
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(Provision $provision): JsonResponse
    {
        $this->authorize('destroy', $provision);

        $provision->delete();

        return $this->restDeleteSuccess();
    }

    /**
     * Clean provisions from prior event.
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function cleanProvisionsFromPriorEvent(): JsonResponse
    {
        $this->authorize('cleanProvisionsFromPriorEvent', [Provision::class]);
        return response()->json(['provisions' => ProvisionMaintenance::cleanProvisionsFromPriorEvent()]);
    }

    /**
     * Mark any unclaimed, non event radio provision as banked. Any unclaimed event radios
     * are expired.
     *
     * We don't check expiration here, that's handled elsewhere.
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function bankProvisions(): JsonResponse
    {
        $this->authorize('bankProvisions', [Provision::class]);
        return response()->json(['provisions' => ProvisionMaintenance::bankProvisions()]);
    }

    /**
     * Expire old provisions.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function expireProvisions(): JsonResponse
    {
        $this->authorize('expireProvisions', Provision::class);
        return response()->json(['provisions' => ProvisionMaintenance::expire()]);
    }


    /**
     * Find all banked items and set the status to available.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws ValidationException
     */

    public function unbankProvisions(): JsonResponse
    {
        $this->authorize('unbankProvisions', Provision::class);
        return response()->json(['provisions' => ProvisionMaintenance::unbankProvisions()]);
    }

    /**
     * Set the status for several provisions owned by the same person at once.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException|UnacceptableConditionException
     */

    public function statuses(Person $person): JsonResponse
    {
        $this->authorize('statuses', [Provision::class, $person]);

        $params = request()->validate([
            'status' => ['required', 'string', Rule::in(Provision::BANKED, Provision::CLAIMED)],
        ]);

        $personId = $person->id;
        if (Provision::haveAllocated($personId)) {
            throw new UnacceptableConditionException("Person has allocated provisions. Earn provisions cannot be adjusted.");
        }

        $status = $params['status'];
        $rows = Provision::retrieveEarned($personId);

        if ($rows->isEmpty()) {
            throw new UnacceptableConditionException("Person has no earned provisions.");
        }

        foreach ($rows as $row) {
            $row->status = $status;
            if ($status == Provision::CLAIMED) {
                $row->consumed_year = current_year();
            }
            $row->saveWithoutValidation();
        }

        return $this->success();
    }

    /**
     * Recommendation report for non-allocated provisions to be un-submitted.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function unsubmitRecommendations(): JsonResponse
    {
        $this->authorize('unsubmitRecommendations', Provision::class);

        return response()->json(ProvisionUnsubmitRecommendationReport::execute());
    }

    /**
     * Un-submit all submitted non-allocated provisions
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function unsubmitProvisions(): JsonResponse
    {
        $this->authorize('unsubmitProvisions', Provision::class);

        $params = request()->validate([
            'people_ids' => 'required|array',
            'people_ids.*' => 'required|integer|exists:person,id',
        ]);

        ProvisionMaintenance::unsubmitProvisions($params['people_ids']);

        return $this->success();
    }
}

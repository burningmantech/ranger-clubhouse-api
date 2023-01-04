<?php

namespace App\Http\Controllers;

use App\Lib\Reports\ProvisionUnsubmitRecommendationReport;
use App\Models\AccessDocument;
use App\Models\Person;
use App\Models\Provision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

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
     * @throws AuthorizationException
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
     * @throws AuthorizationException
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
     * @throws AuthorizationException
     */

    public function cleanProvisionsFromPriorEvent(): JsonResponse
    {
        $this->authorize('cleanProvisionsFromPriorEvent', [Provision::class]);

        $user = $this->user->callsign;
        $rows = Provision::whereIn('status', [Provision::CLAIMED, Provision::SUBMITTED])
            ->where('is_allocated', false)
            ->with('person:id,callsign,status')
            ->get();
        $rows = $rows->sortBy('person.callsign', SORT_NATURAL | SORT_FLAG_CASE);

        $provisions = [];

        $reasonExpired = 'marked as expired via maintenance function';
        $reasonUsed = 'marked as used via maintenance function';
        foreach ($rows as $prov) {
            $prov->status = Provision::USED;
            $prov->addComment($reasonUsed, $user);
            $prov->auditReason = $reasonUsed;
            $this->saveProvision($prov, $provisions);
        }

        // Expire or use all job provisions.
        $rows = Provision::where('is_allocated', true)
            ->whereIn('status', [Provision::AVAILABLE, Provision::CLAIMED, Provision::SUBMITTED, Provision::BANKED])
            ->with('person:id,callsign,status')
            ->get();

        foreach ($rows as $row) {
            if ($row->type == Provision::SUBMITTED || $row->type == Provision::CLAIMED) {
                $row->status = Provision::USED;
                $row->addComment($reasonUsed, $user);
                $row->auditReason = $reasonUsed;
            } else {
                $row->status = Provision::EXPIRED;
                $row->addComment($reasonExpired, $user);
                $row->auditReason = $reasonExpired;
            }
            $this->saveProvision($row, $provisions);
        }

        return response()->json(['provisions' => $provisions]);
    }

    /**
     * Mark any qualified provisions as banked.
     * We don't check expiration here, that's handled elsewhere.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function bankProvisions(): JsonResponse
    {
        $this->authorize('bankProvisions', [Provision::class]);

        $user = $this->user->callsign;

        $rows = Provision::where('status', Provision::AVAILABLE)
            ->where('is_allocated', false)
            ->with('person:id,callsign,status')
            ->get();

        $provisions = [];
        foreach ($rows as $p) {
            $p->status = Provision::BANKED;
            $p->addComment('marked as banked via maintenance function', $user);
            $this->saveProvision($p, $provisions);
        }

        usort($provisions, fn($a, $b) => strcasecmp($a['person']['callsign'], $b['person']['callsign']));

        return response()->json(['provisions' => $provisions]);
    }

    /**
     * Expire old provisions.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function expireProvisions(): JsonResponse
    {
        $this->authorize('expireProvisions', [Provision::class]);

        $user = $this->user->callsign;

        $rows = Provision::whereIn('status', [Provision::BANKED, Provision::CLAIMED, Provision::AVAILABLE])
            ->where('expires_on', '<=', now())
            ->with('person:id,callsign,status,email')
            ->get();

        $provisions = [];
        foreach ($rows as $p) {
            $p->status = Provision::EXPIRED;
            $p->addComment('marked as expired via maintenance function', $user);
            $this->saveProvision($p, $provisions, true);
        }

        usort($provisions, fn($a, $b) => strcasecmp($a['person']['callsign'], $b['person']['callsign']));

        return response()->json(['provisions' => $provisions]);
    }


    /**
     * Save the provision, log the changes, and build a response.
     *
     * @param Provision $p
     * @param $provisions
     * @param bool $includeEmail
     */

    private function saveProvision(Provision $p, &$provisions, bool $includeEmail = false)
    {
        $p->save();

        $person = $p->person;
        $result = [
            'id' => $p->id,
            'type' => $p->type,
            'status' => $p->status,
            'source_year' => $p->source_year,
            'person' => [
                'id' => $p->person_id,
                'callsign' => $person->callsign ?? ('Person #' . $p->person_id),
                'status' => $person->status ?? 'unknown',
            ]
        ];

        if ($includeEmail && $person) {
            $result['person']['email'] = $person->email;
        }

        $provisions[] = $result;
    }

    /**
     * Find all banked items and set the status to available.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function unbankProvisions(): JsonResponse
    {
        $this->authorize('unbankProvisions', Provision::class);

        $rows = Provision::where('status', Provision::BANKED)
            ->with('person:id,callsign,status')
            ->get();

        $provisions = [];
        foreach ($rows as $row) {
            $row->auditReason = 'maintenance - unbank provisions';
            $row->status = Provision::AVAILABLE;
            $row->addComment('marked as available via maintenance function', $this->user->callsign);
            $row->saveWithoutValidation();
            $this->saveProvision($row, $provisions, true);
        }

        usort($provisions, fn($a, $b) => strcasecmp($a['person']['callsign'], $b['person']['callsign']));

        return response()->json(['provisions' => $provisions]);
    }

    /**
     * Expire job provisions.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function expireJobProvisions(): JsonResponse
    {
        $this->authorize('expireJobProvisions', Provision::class);

        // Expire or use all allocated items.
        $rows = Provision::where('is_allocated', true)
            ->whereIn('status', [Provision::SUBMITTED, Provision::AVAILABLE, Provision::BANKED])
            ->with('person:id,callsign,status')
            ->get();

        $user = $this->user;
        $reasonUsed = 'maintenance function - marked submitted job provision used';
        $reasonExpired = 'maintenance function - marked available job provision expired';
        $provisions = [];

        foreach ($rows as $row) {
            if ($row->type == Provision::SUBMITTED) {
                $row->status = Provision::USED;
                $row->addComment($reasonUsed, $user);
                $row->auditReason = $reasonUsed;
            } else {
                $row->status = AccessDocument::EXPIRED;
                $row->addComment($reasonExpired, $user);
                $row->auditReason = $reasonExpired;
            }
            $this->saveProvision($row, $documents);
        }

        return response()->json(['provisions' => $provisions]);
    }

    /**
     * Set the status for several provisions owned by the same person at once.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function statuses(Person $person): JsonResponse
    {
        $this->authorize('statuses', [Provision::class, $person]);

        $params = request()->validate([
            'status' => ['required', 'string', Rule::in(Provision::BANKED, Provision::CLAIMED)],
        ]);

        $personId = $person->id;
        if (Provision::haveAllocated($personId)) {
            throw new InvalidArgumentException("Person has allocated provisions. Earn provisions cannot be adjusted.");
        }

        $status = $params['status'];
        $rows = Provision::retrieveEarned($personId);

        if ($rows->isEmpty()) {
            throw new InvalidArgumentException("Person has no earned provisions.");
        }

        foreach ($rows as $row) {
            $row->status = $status;
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

        foreach ($params['people_ids'] as $personId) {
            $provisions = Provision::where('person_id', $personId)
                ->whereIn('status', [Provision::CLAIMED, Provision::SUBMITTED])
                ->where('is_allocated', false)
                ->get();

            foreach ($provisions as $provision) {
                $provision->status = Provision::AVAILABLE;
                $provision->auditReason = 'un-submit maintenance function';
                $provision->additional_comments = 'un-submit maintenance function';
                $provision->save();
            }
        }

        return $this->success();
    }
}

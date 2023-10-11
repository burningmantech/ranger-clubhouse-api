<?php

namespace App\Http\Controllers;

use App\Lib\HandleReservationUpload;
use App\Models\HandleReservation;
use App\Models\Person;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HandleReservationController extends ApiController
{

    /**
     * Display a listing of handle reservations.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', HandleReservation::class);
        $params = request()->validate([
            'active' => 'sometimes|boolean',
            'reservation_type' => 'sometimes|string',
            'twii_year' => 'sometimes|integer'
        ]);

        return $this->success(HandleReservation::findForQuery($params), null, 'handle_reservation');
    }

    /**
     * Store a newly created handle reservation in the database.
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */
    public function store(): JsonResponse
    {
        $this->authorize('create', HandleReservation::class);
        $handleReservation = new HandleReservation;
        $this->fromRest($handleReservation);

        if ($handleReservation->save()) {
            return $this->success($handleReservation);
        }

        return $this->restError($handleReservation);
    }

    /**
     * Display the specified handle reservation.
     *
     * @param HandleReservation $handleReservation
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function show(HandleReservation $handleReservation): JsonResponse
    {
        $this->authorize('view', HandleReservation::class);
        return $this->success($handleReservation);
    }

    /**
     * Update the specified handle reservation in the database.
     *
     * @param HandleReservation $handleReservation
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */
    public function update(HandleReservation $handleReservation): JsonResponse
    {
        $this->authorize('update', HandleReservation::class);
        $this->fromRest($handleReservation);

        if ($handleReservation->save()) {
            return $this->success($handleReservation);
        }

        return $this->restError($handleReservation);
    }

    /**
     * Remove the specified handle reservation from the database.
     *
     * @param HandleReservation $handleReservation
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function destroy(HandleReservation $handleReservation): JsonResponse
    {
        $this->authorize('delete', HandleReservation::class);
        $handleReservation->delete();
        return $this->restDeleteSuccess();
    }

    /**
     * Bulk upload handles
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function upload(): JsonResponse
    {
        $this->authorize('upload', HandleReservation::class);

        $params = request()->validate([
            'handles' => 'required|string',
            'reason' => 'sometimes|string',
            'expires_on' => 'sometimes|date',
            'reservation_type' => [
                'required',
                Rule::in(
                    HandleReservation::TYPE_BRC_TERM,
                    HandleReservation::TYPE_DECEASED_PERSON,
                    HandleReservation::TYPE_DISMISSED_PERSON,
                    HandleReservation::TYPE_RADIO_JARGON,
                    HandleReservation::TYPE_RANGER_TERM,
                    HandleReservation::TYPE_SLUR,
                    HandleReservation::TYPE_TWII_PERSON,
                    HandleReservation::TYPE_UNCATEGORIZED
                )
            ],
            'twii_year' => 'sometimes|integer|required_if:reservation_type,' . HandleReservation::TYPE_TWII_PERSON,
            'commit' => 'required|boolean',
        ]);

        return response()->json(HandleReservationUpload::execute(
            $params['handles'],
            $params['reservation_type'],
            $params['expires_on'] ?? null,
            $params['reason'] ?? '',
            $params['twii_year'] ?? null,
            $params['commit'],
        ));
    }

    /**
     * Expire handles
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function expire(): JsonResponse
    {
        $this->authorize('expire', HandleReservation::class);

        $rows = HandleReservation::findForQuery(['expired' => true]);
        foreach ($rows as $row) {
            $row->auditReason = 'expire handle';
            $row->delete();
        }

        return response()->json(['expired' => $rows->count()]);
    }

    const EXCLUDE_STATUSES = [Person::PAST_PROSPECTIVE, Person::AUDITOR];

    /**
     * List all handles. Used by the Handle Checker.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function handles(): JsonResponse
    {
        $this->authorize('handles', HandleReservation::class);

        $result = [];
        $rangerHandles = DB::table('person')
            ->select('id', 'callsign', 'status', 'vintage')
            ->whereNotIn('status', self::EXCLUDE_STATUSES)
            ->orWhere('vintage', true)
            ->orderBy('status')
            ->orderBy('callsign')
            ->get();

        foreach ($rangerHandles as $ranger) {
            $result[] = $this->buildHandle(
                $ranger->callsign,
                $ranger->status,
                null,
                ['id' => $ranger->id, 'status' => $ranger->status, 'vintage' => $ranger->vintage]
            );
        }

        $handleReservations = HandleReservation::findForQuery(['active' => true]);
        foreach ($handleReservations as $reservation) {
            $result[] = $this->buildHandle($reservation->handle, $reservation->reservation_type, $reservation->reason);
        }

        return response()->json(['handles' => $result]);
    }

    private function buildHandle(string $name, string $entityType, ?string $reason, array $person = null): array
    {
        $result = ['name' => $name, 'entityType' => $entityType];
        if (!empty($reason)) {
            $result['reason'] = $reason;
        }
        if ($person) {
            $result['personId'] = $person['id'];
            $result['personStatus'] = $person['status'];
            $result['personVintage'] = $person['vintage'];
        }
        return $result;
    }

}

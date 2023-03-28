<?php

namespace App\Http\Controllers;

use App\Lib\ReservedCallsigns;
use App\Models\HandleReservation;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** HTTP controller for handles (person callsigns and reserved words), used by the handle checker. */
class HandleController extends ApiController
{
    const EXCLUDE_STATUSES = [Person::PAST_PROSPECTIVE, Person::AUDITOR];

    /**
     * List all handles.
     * @return JsonResponse
     */

    public function index(): JsonResponse
    {
        $result = array();
        $rangerHandles = DB::table('person')
            ->select('id', 'callsign', 'status', 'vintage')
            ->whereNotIn('status', self::EXCLUDE_STATUSES)
            ->orWhere('vintage', true)
            ->get();
        foreach ($rangerHandles as $ranger) {
            $result[] = $this->jsonHandle(
                $ranger->callsign,
                "$ranger->status ranger",
                ['id' => $ranger->id, 'status' => $ranger->status, 'vintage' => $ranger->vintage]
            );
        }
        $handleReservations = HandleReservation::findAll(true);
        foreach ($handleReservations as $reservation) {
            $result[] = $this->jsonHandle($reservation->handle, $reservation->reservation_type);
        }
        foreach (ReservedCallsigns::PHONETIC as $handle) {
            $result[] = $this->jsonHandle($handle, 'phonetic-alphabet');
        }
        foreach (ReservedCallsigns::LOCATIONS as $handle) {
            $result[] = $this->jsonHandle($handle, 'location');
        }
        foreach (ReservedCallsigns::RADIO_JARGON as $handle) {
            $result[] = $this->jsonHandle($handle, 'jargon');
        }
        foreach (ReservedCallsigns::RANGER_JARGON as $handle) {
            $result[] = $this->jsonHandle($handle, 'jargon');
        }
        foreach (ReservedCallsigns::twiiVips() as $handle) {
            $result[] = $this->jsonHandle($handle, 'VIP');
        }
        foreach (ReservedCallsigns::RESERVED as $handle) {
            $result[] = $this->jsonHandle($handle, 'reserved');
        }
        return response()->json(['data' => $result]);
    }

    private function jsonHandle(string $name, string $entityType, array $person = null): array
    {
        $result = ['name' => $name, 'entityType' => $entityType];
        if ($person) {
            $result['personId'] = $person['id'];
            $result['personStatus'] = $person['status'];
            $result['personVintage'] = $person['vintage'];
        }
        return $result;
    }
}

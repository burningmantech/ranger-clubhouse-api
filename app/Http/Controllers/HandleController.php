<?php
namespace App\Http\Controllers;

use App\Helpers\ReservedCallsigns;
use Illuminate\Support\Facades\DB;

/** HTTP controller for handles (person callsigns and reserved words), used by the handle checker. */
class HandleController extends ApiController
{
    const EXCLUDE_STATUSES = ['past prospective', 'auditor', 'resigned', 'deceased', 'uberbonked', 'dismissed'];
    /**
     * List all handles.
     * @return \Illuminate\Http\Response
     */
    public function index()
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
        foreach (ReservedCallsigns::$PHONETIC as $handle) {
            $result[] = $this->jsonHandle($handle, 'phonetic-alphabet');
        }
        foreach (ReservedCallsigns::$LOCATIONS as $handle) {
            $result[] = $this->jsonHandle($handle, 'location');
        }
        foreach (ReservedCallsigns::$RADIO_JARGON as $handle) {
            $result[] = $this->jsonHandle($handle, 'jargon');
        }
        foreach (ReservedCallsigns::$RANGER_JARGON as $handle) {
            $result[] = $this->jsonHandle($handle, 'jargon');
        }
        foreach (ReservedCallsigns::$VIPS as $handle) {
            $result[] = $this->jsonHandle($handle, 'VIP');
        }
        foreach (ReservedCallsigns::$RESERVED as $handle) {
            $result[] = $this->jsonHandle($handle, 'reserved');
        }
        return response()->json(['data' => $result]);
    }

    private function jsonHandle(string $name, string $entityType, array $person = null)
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

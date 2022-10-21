<?php

namespace App\Lib;

use App\Models\Person;
use App\Models\PersonAward;
use App\Models\Timesheet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BulkGrantAward
{
    /**
     * Find people who might qualify to receive a service award and grant them one.
     *
     * @param int $awardId
     * @param int $serviceYears
     * @param bool $commit
     * @return array
     */

    public static function grantServiceYearsAward(int $awardId, int $serviceYears, bool $commit): array
    {
        // Find accounts which are still active (active, inactive, retired, etc.)
        $ids = DB::table('person')
            ->select('id')
            ->whereIn('status', Person::ACTIVE_STATUSES)
            ->get()
            ->pluck('id')
            ->toArray();

        $chunkedIds = array_chunk($ids, 300);

        $candidates = [];
        foreach ($chunkedIds as $chunk) {
            $rangerYears = Timesheet::yearsRangeredCountForIds($chunk);

            foreach ($rangerYears as $personId => $years) {
                if ($years >= $serviceYears) {
                    $candidates[$personId] = $years;
                }
            }
        }

        // Check to see who already has the award
        $existingAward = PersonAward::haveAwardForIds($awardId, array_keys($candidates));
        foreach ($existingAward as $personId) {
            unset($candidates[$personId]);
        }

        if (empty($candidates)) {
            return [];
        }

        $people = DB::table('person')
            ->select('id', 'callsign', 'status')
            ->whereIn('id', array_keys($candidates))
            ->orderBy('callsign')
            ->get();

        $results = [];
        foreach ($people as $person) {
            if ($commit) {
                PersonAward::create([
                    'person_id' => $person->id,
                    'award_id' => $awardId,
                    'notes' => 'Bulk granted by ' . Auth::user()?->callsign,
                ]);
            }

            $results[] = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'status' => $person->status,
                'years' => $candidates[$person->id] ?? 0,
            ];
        }

        return $results;
    }

    /**
     * Bulk grant a list of callsigns to give an award to
     *
     * @param int $awardId
     * @param string $callsigns
     * @param bool $commit
     * @return array
     */

    public static function bulkGrant(int $awardId, string $callsigns, bool $commit): array
    {
        $lines = explode("\n", str_replace("\r", "", $callsigns));
        $callsigns = [];
        $records = [];
        foreach ($lines as $callsign) {
            $callsign = trim($callsign);
            if (empty($callsign)) {
                continue;
            }

            $records[] = (object)[
                'callsign' => $callsign,
            ];

            $callsigns[] = $callsign;
        }

        $people = Person::findAllByCallsigns($callsigns);

        foreach ($records as $record) {
            $person = $people[Person::normalizeCallsign($record->callsign)] ?? null;
            if ($person) {
                $personId = $person->id;
                $record->id = $personId;
                $record->callsign = $person->callsign;
                $record->status = $person->status;
                if (PersonAward::haveAward($awardId, $personId)) {
                    $record->have_award = true;
                } else if ($commit) {
                    PersonAward::create([
                        'person_id' => $personId,
                        'award_id' => $awardId,
                        'notes' => 'bulk granted by ' . Auth::user()?->callsign,
                    ]);
                    $record->have_award = true;
                }
            } else {
                $record->not_found = true;
            }
        }

        return $records;
    }
}
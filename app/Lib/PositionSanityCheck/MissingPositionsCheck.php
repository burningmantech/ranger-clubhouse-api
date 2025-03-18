<?php

namespace App\Lib\PositionSanityCheck;

use App\Models\Person;
use App\Models\PersonPosition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MissingPositionsCheck extends SanityCheck
{
    public static function issues(): array
    {
        $peopleById = [];
        $allRangersPositions = DB::table('position')
            ->where('all_rangers', true)
            ->where('active', true)
            ->orderBy('title')
            ->get();

        if ($allRangersPositions->isNotEmpty()) {
            $actives = self::retrieveAccounts($allRangersPositions, [Person::ACTIVE, Person::INACTIVE, Person::INACTIVE_EXTENSION]);

            foreach ($actives as $row) {
                $peopleById[$row->id] = [
                    "id" => $row->id,
                    "callsign" => $row->callsign,
                    "status" => $row->status,
                    'positions' => self::scanForMissing($row, $allRangersPositions),
                ];
            }
        }

        $allUserPositions = DB::table('position')
            ->where('active', true)
            ->where('new_user_eligible', true)
            ->get();

        if ($allUserPositions->isNotEmpty()) {
            $allUsers = self::retrieveAccounts($allUserPositions, [
                Person::ACTIVE,
                Person::ALPHA,
                Person::AUDITOR,
                Person::INACTIVE,
                Person::INACTIVE_EXTENSION,
                Person::PROSPECTIVE,
                Person::ECHELON,
            ]);

            foreach ($allUsers as $row) {
                $missing = self::scanForMissing($row, $allUserPositions);
                if (!isset($peopleById[$row->id])) {
                    $peopleById[$row->id] = [
                        "id" => $row->id,
                        "callsign" => $row->callsign,
                        "status" => $row->status,
                        "positions" => $missing,
                    ];
                } else {
                    $peopleById[$row->id]['positions'] = [...$peopleById[$row->id]['positions'], ...$missing];
                }
            }
        }

        $people = array_values($peopleById);
        usort($people, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));

        return $people;
    }

    public static function retrieveAccounts($positions, $statuses): Collection
    {
        $positionIds = $positions->pluck('id');
        return Person::select('person.id', 'person.callsign', 'person.status')
            ->whereIn('person.status', $statuses)
            ->whereRaw('(SELECT COUNT(*) FROM person_position WHERE person_id=person.id AND position_id IN (' . $positionIds->implode(',') . ')) != ' . $positionIds->count())
            ->orderBy('callsign')
            ->with('person_position')
            ->get();
    }

    public static function repair($peopleIds, ...$options): array
    {
        $results = [];

        $people = Person::select('id', 'callsign', 'status')
            ->whereIn('id', $peopleIds)
            ->with('person_position')
            ->orderBy('callsign')
            ->get();

        $positions = DB::table('position')
            ->where('active', true)
            ->where(function ($w) {
                $w->where('all_rangers', true);
                $w->orWhere('new_user_eligible', true);
            })->orderBy('title')
            ->get();

        foreach ($people as $person) {
            $messages = [];
            $missingIds = [];
            foreach ($positions as $position) {
                if (!$position->new_user_eligible && !in_array($person->status, Person::ACTIVE_STATUSES)) {
                    continue;
                }

                if (PersonPosition::havePosition($person->id, $position->id)) {
                    continue;
                }

                $missingIds[] = $position->id;
                $messages[] = 'granted ' . $position->title;
            }

            if (empty($missingIds)) {
                $messages[] = 'has all default positions';
            } else {
                PersonPosition::addIdsToPerson($person->id, $missingIds, 'position sanity checker repair');
            }

            $results[] = ['id' => $person->id, 'messages' => $messages];
        }
        return $results;
    }

    public static function scanForMissing($person, $positions): array
    {
        $positionsById = $person->person_position->keyBy('position_id');

        $missing = [];
        foreach ($positions as $position) {
            if (!$positionsById->has($position->id)) {
                $missing[] = [
                    'id' => $position->id,
                    'title' => $position->title,
                ];
            }
        }

        return $missing;
    }
}

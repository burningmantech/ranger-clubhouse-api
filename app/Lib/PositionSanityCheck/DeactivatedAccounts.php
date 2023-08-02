<?php

namespace App\Lib\PositionSanityCheck;

use App\Models\Person;
use App\Models\PersonPosition;
use Illuminate\Support\Facades\DB;

class DeactivatedAccounts
{
    const NO_POSITIONS_STATUSES = [
        Person::BONKED,
        Person::DECEASED,
        Person::DISMISSED,
        Person::PAST_PROSPECTIVE,
        Person::RESIGNED,
        Person::UBERBONKED
    ];

    public static function issues(): array
    {
        return DB::table('person')
            ->select('id', 'callsign', 'status')
            ->whereIn('status', self::NO_POSITIONS_STATUSES)
            ->whereExists(function ($q) {
                $q->from('person_position')
                    ->select(DB::raw(1))
                    ->whereColumn('person_position.person_id', 'person.id')
                    ->limit(1);
            })
            ->orderBy('callsign')
            ->get()
            ->toArray();
    }

    public static function repair($peopleIds, ...$options): array
    {
        $results = [];

        foreach ($peopleIds as $personId) {
            PersonPosition::resetPositions($personId, 'position sanity checker - deactivated account', Person::REMOVE_ALL);
            $results[] = ['id' => $personId, 'messages' => ['Positions revoked']];
        }
        return $results;
    }
}
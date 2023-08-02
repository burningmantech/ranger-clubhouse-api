<?php

namespace App\Lib\PositionSanityCheck;

use App\Models\Person;
use App\Models\PersonPosition;
use Illuminate\Support\Facades\DB;

class RetiredAccounts
{
    public static function issues(): array
    {
        $positionIds = DB::table('position')->where('active', true)->where('new_user_eligible', true)->pluck('id');

        if ($positionIds->isEmpty()) {
            // Highly unlikely but ya never know.
            return [];
        }

        return DB::table('person')
            ->select('id', 'callsign', 'status')
            ->where('status', Person::RETIRED)
            ->whereExists(function ($q) use ($positionIds) {
                $q->from('person_position')
                    ->select(DB::raw(1))
                    ->whereColumn('person_position.person_id', 'person.id')
                    ->whereNotIn('person_position.position_id', $positionIds)
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
            PersonPosition::resetPositions($personId, 'position sanity checker - retired account', Person::ADD_NEW_USER);
            $results[] = ['id' => $personId, 'messages' => ['Positions adjusted']];
        }
        return $results;
    }

}
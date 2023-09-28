<?php

namespace App\Lib\PositionSanityCheck;

use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\PersonTeam;
use Illuminate\Support\Facades\DB;

class DeactivatedAccounts
{
    public static function issues(): array
    {
        return DB::table('person')
            ->select('id', 'callsign', 'status')
            ->whereIn('status', Person::DEACTIVATED_STATUSES)
            ->where(function ($w) {
                $w->whereExists(function ($q) {
                    $q->from('person_position')
                        ->select(DB::raw(1))
                        ->whereColumn('person_position.person_id', 'person.id')
                        ->limit(1);
                });
                $w->orWhereExists(function ($q) {
                    $q->from('person_team')
                        ->select(DB::raw(1))
                        ->whereColumn('person_team.person_id', 'person.id')
                        ->limit(1);
                });
            })->orderBy('callsign')
            ->get()
            ->toArray();
    }

    public static function repair($peopleIds, ...$options): array
    {
        $results = [];

        foreach ($peopleIds as $personId) {
            PersonPosition::resetPositions($personId, 'position sanity checker - deactivated account', Person::REMOVE_ALL);
            PersonTeam::removeAllForPerson($personId, 'position sanity checker - deactivated account');
            $results[] = ['id' => $personId, 'messages' => ['Team & Positions revoked']];
        }
        return $results;
    }
}
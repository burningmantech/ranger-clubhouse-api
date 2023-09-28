<?php

namespace App\Lib\PositionSanityCheck;

use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\PersonRole;
use App\Models\PersonTeam;
use Illuminate\Support\Facades\DB;

class DeactivatedAccounts
{
    const REASON = 'position sanity checker - deactivated account';

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
                $w->orWhereExists(function ($q) {
                    $q->from('person_role')
                        ->select(DB::raw(1))
                        ->whereColumn('person_role.person_id', 'person.id')
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
            PersonPosition::resetPositions($personId, self::REASON, Person::REMOVE_ALL);
            PersonTeam::removeAllFromPerson($personId, self::REASON);
            PersonRole::removeAllFromPerson($personId, self::REASON);
            $results[] = ['id' => $personId, 'messages' => ['Team, Positions and/or Permissions revoked']];
        }
        return $results;
    }
}
<?php

namespace App\Lib\PositionSanityCheck;

use App\Models\Person;
use App\Models\Position;
use App\Models\PersonPosition;
use App\Models\PersonMentor;

use Illuminate\Support\Facades\DB;

class ShinnyPenniesCheck extends SanityCheck
{
    public static function issues(): array
    {
        return DB::table('person')
            ->where('person.status', Person::ACTIVE)
            ->whereExists(function ($sql) {
                $sql->from('timesheet')
                    ->select(DB::raw(1))
                    ->where('position_id', Position::ALPHA)
                    ->whereYear('on_duty', current_year())
                    ->whereColumn('person.id', 'timesheet.person_id')
                    ->limit(1);
            })->whereNotExists(function ($sql) {
                $sql->from('person_position')
                    ->select(DB::raw(1))
                    ->whereColumn('person.id', 'person_position.person_id')
                    ->where('person_position.position_id', Position::DIRT_SHINY_PENNY)
                    ->limit(1);
            })->orderBy('callsign')
            ->get()
            ->toArray();
    }

    public static function repair($peopleIds, ...$options): array
    {
        $results = [];
        $year = current_year();

        foreach ($peopleIds as $personId) {
            $hasPenny = PersonPosition::havePosition($personId, Position::DIRT_SHINY_PENNY);
            $isPenny = PersonMentor::retrieveYearPassed($personId) == $year;

            if ($hasPenny && !$isPenny) {
                PersonPosition::removeIdsFromPerson($personId, [Position::DIRT_SHINY_PENNY], 'position sanity checker repair');
                $message = 'not a Shiny Penny, position removed';
            } elseif (!$hasPenny && $isPenny) {
                PersonPosition::addIdsToPerson($personId, [Position::DIRT_SHINY_PENNY], 'position sanity checker repair');
                $message = 'is a Shiny Penny, position added';
            } else {
                $message = 'Shiny Penny already has position. no repair needed.';
            }
            $results[] = ['id' => $personId, 'messages' => [$message]];
        }
        return $results;
    }
}

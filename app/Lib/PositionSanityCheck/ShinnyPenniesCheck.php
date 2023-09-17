<?php

namespace App\Lib\PositionSanityCheck;

use App\Models\Person;
use App\Models\PersonMentor;
use App\Models\PersonPosition;
use App\Models\Position;
use App\Models\Timesheet;
use Illuminate\Support\Facades\DB;

class ShinnyPenniesCheck extends SanityCheck
{
    public static function issues(): array
    {
        $year = current_year();
        $alphaIds = DB::table('timesheet')
            ->whereYear('on_duty', current_year())
            ->where('position_id', Position::ALPHA)
            ->orderBy('person_id')
            ->pluck('person_id');

        if ($alphaIds->isNotEmpty()) {
            $noPosition = DB::table('person')
                ->select('person.id', 'person.callsign', 'person.status')
                ->where('person.status', Person::ACTIVE)
                ->whereIntegerInRaw('person.id', $alphaIds)
                ->whereNotExists(function ($sql) use ($alphaIds) {
                    $sql->from('person_position')
                        ->select(DB::raw(1))
                        ->whereColumn('person.id', 'person_position.person_id')
                        ->where('person_position.position_id', Position::DIRT_SHINY_PENNY)
                        ->limit(1);
                })->orderBy('callsign')
                ->get()
                ->toArray();

            foreach ($noPosition as $p) {
                $p->has_shiny_penny = false;
                $p->year = $year;
            }
        } else {
            $noPosition = [];
        }

        $sql = DB::table('person_position')
            ->where('position_id', Position::DIRT_SHINY_PENNY);

        if ($alphaIds->isNotEmpty()) {
            $sql->whereIntegerNotInRaw('person_id', $alphaIds);
        }

        $personIds = $sql->pluck('person_id');

        if ($personIds->isNotEmpty()) {
            $havePosition = DB::table('person')
                ->select(
                    'person.id', 'person.callsign', 'person.status',
                    DB::raw('(SELECT mentor_year FROM person_mentor WHERE person_mentor.person_id=person.id ORDER BY person_mentor.mentor_year DESC LIMIT 1) as year')
                )
                ->whereIntegerInRaw('id', $personIds)
                ->get()
                ->toArray();
            foreach ($havePosition as $p) {
                $p->has_shiny_penny = true;
            }
        } else {
            $havePosition = [];
        }

        $results = array_merge($noPosition, $havePosition);
        usort($results, fn($a, $b) => strcasecmp($a->callsign, $b->callsign));

        return $results;
    }

    public static function repair($peopleIds, ...$options): array
    {
        $results = [];
        $year = current_year();

        foreach ($peopleIds as $personId) {
            $hasPenny = PersonPosition::havePosition($personId, Position::DIRT_SHINY_PENNY);
            $isPenny = Timesheet::findLatestForPersonPosition($personId, Position::ALPHA, $year) != null;

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

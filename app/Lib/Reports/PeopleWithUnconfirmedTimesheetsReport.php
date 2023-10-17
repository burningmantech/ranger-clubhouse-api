<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Timesheet;
use Illuminate\Support\Facades\DB;

class PeopleWithUnconfirmedTimesheetsReport
{
    /*
     * Retrieve all people who has not indicated their timesheet entries are correct.
     */

    public static function execute(int $year): array
    {
        $rows = Person::select(
            'id', 'callsign', 'first_name', 'last_name', 'email', 'home_phone',
        )->addSelect([
            'unverified_count' => function ($q) use ($year) {
                $q->from('timesheet')
                    ->selectRaw('count(*)')
                    ->whereColumn('person.id', 'timesheet.person_id')
                    ->whereYear('on_duty', $year)
                    ->whereIn('timesheet.review_status', [Timesheet::STATUS_UNVERIFIED, Timesheet::STATUS_REJECTED]);
            }
        ])->leftJoin('person_event', function ($j) use ($year) {
            $j->on('person_event.person_id', 'person.id');
            $j->where('person_event.year', $year);
        })->whereIn('person.status', Person::ACTIVE_STATUSES)
            ->whereRaw('IFNULL(person_event.timesheet_confirmed, FALSE) != TRUE')
            ->whereExists(function ($sql) use ($year) {
                $sql->from('timesheet')
                    ->select(DB::raw(1))
                    ->whereColumn('person.id', 'timesheet.person_id')
                    ->whereYear('on_duty', $year)
                    ->limit(1);
            })->orderBy('callsign')
            ->get();

        $people = [];
        foreach ($rows as $person) {
            $people[] = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'email' => $person->email,
                'home_phone' => $person->home_phone,
                'unverified_count' => $person->unverified_count,
            ];
        }

        return $people;
    }
}
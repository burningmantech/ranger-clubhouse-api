<?php

namespace App\Lib\Reports;

use Illuminate\Support\Facades\DB;

class PeopleWithUnconfirmedTimesheetsReport
{
    /*
     * Retrieve all people who has not indicated their timesheet entries are correct.
     */

    public static function execute(int $year)
    {
        return DB::select(
            "SELECT person.id, callsign, first_name, last_name, email, home_phone,
                    (SELECT count(*) FROM timesheet
                        WHERE person.id=timesheet.person_id
                          AND YEAR(timesheet.on_duty)=?
                          AND  timesheet.review_status not in ('verified', 'rejected')  ) as unverified_count
               FROM person
               LEFT JOIN person_event ON person_event.person_id=person.id AND person_event.year=?
               WHERE status in ('active', 'inactive', 'inactive extension', 'retired')
                 AND IFNULL(person_event.timesheet_confirmed, FALSE) != TRUE
                 AND EXISTS (SELECT 1 FROM timesheet WHERE timesheet.person_id=person.id AND YEAR(timesheet.on_duty)=?)
               ORDER BY callsign",
            [$year, $year, $year]
        );
    }
}
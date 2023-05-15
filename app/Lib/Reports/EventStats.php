<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\PersonMentor;
use App\Models\Position;
use Illuminate\Support\Facades\DB;

class EventStats
{
    public static function execute(int $year): array
    {

        $alphaIds = DB::table('timesheet')
            ->select('person_id')
            ->whereYear('on_duty', $year)
            ->where('position_id', Position::ALPHA)
            ->groupBy('person_id')
            ->get()
            ->pluck('person_id');

        // How many people signed up to work
        $sql = DB::table('slot')
            ->join('position', 'slot.position_id', 'position.id')
            ->join('person_slot', 'person_slot.slot_id', 'slot.id');
        if (!empty($alphaIds)) {
            $sql->whereIntegerNotInRaw('person_slot.person_id', $alphaIds);
        }

        $rangerSignups = $sql->where('begins_year', $year)
            ->where('position_id', '!=', Position::ALPHA)
            ->where('position.type', '!=', Position::TYPE_TRAINING)
            ->distinct('person_slot.person_id')
            ->count();

        $workingShinyPennies = 0;
        if (!empty($alphaIds)) {
            $workingShinyPennies = DB::table('timesheet')
                ->join('person', 'timesheet.person_id', 'person.id')
                ->join('person_status', 'person_status.person_id', 'timesheet.person_id')
                ->join('position', 'timesheet.position_id', 'position.id')
                ->whereYear('on_duty', $year)
                ->whereIntegerInRaw('timesheet.person_id', $alphaIds)
                ->whereYear('person_status.created_at', $year)
                ->where('person_status.new_status', Person::ACTIVE)
                ->where('position_id', '!=', Position::ALPHA)
                ->where('position.type', '!=', Position::TYPE_TRAINING)
                ->distinct('timesheet.person_id')
                ->count();
        }

        return [
            // Total number of timesheet entries created
            'timesheets_total' => DB::table('timesheet')
                ->whereYear('on_duty', $year)
                ->count(),

            // Alpha entry count
            'timesheets_alpha' => DB::table('timesheet')
                ->whereYear('on_duty', $year)
                ->where('position_id', Position::ALPHA)
                ->count(),

            // People worked
            'working_people' => DB::table('timesheet')
                ->join('position', 'timesheet.position_id', 'position.id')
                ->whereYear('on_duty', $year)
                ->where('position_id', '!=', Position::ALPHA)
                ->where('position.type', '!=', Position::TYPE_TRAINING)
                ->distinct('person_id')
                ->count(),

            // Shiny Pennies who were minted but did not work.
            'working_shiny_pennies' => $workingShinyPennies,

            // How many people marked on site
            'on_site' => DB::table('person')->where('on_site', true)->count(),

            // How many alphas walked with a mentor
            'alphas_walked' => DB::table('person_mentor')
                ->where('mentor_year', $year)
                ->distinct('person_id')
                ->count(),

            // How many alphas were minted as Shiny Pennies
            'alphas_passed' => DB::table('person_mentor')
                ->where('mentor_year', $year)
                ->where('status', PersonMentor::PASS)
                ->distinct('person_id')
                ->count(),

            // How many alphas bonked or self-bonked.
            'alphas_bonked' => DB::table('person_mentor')
                ->where('mentor_year', $year)
                ->whereNotIn('status', [PersonMentor::PASS, PersonMentor::PENDING])
                ->distinct('person_id')
                ->count(),

            // How many alphas current walking with a mentor
            'alphas_walking' => DB::table('person_mentor')
                ->where('mentor_year', $year)
                ->where('status', PersonMentor::PENDING)
                ->distinct('person_id')
                ->count(),

            // How many people signed up to work
            'working_people_estimate' => DB::table('slot')
                ->join('position', 'slot.position_id', 'position.id')
                ->join('person_slot', 'person_slot.slot_id', 'slot.id')
                ->where('begins_year', $year)
                ->where('position_id', '!=', Position::ALPHA)
                ->where('position.type', '!=', Position::TYPE_TRAINING)
                ->distinct('person_slot.person_id')
                ->count(),

            'rangers_working_estimate' => $rangerSignups,
        ];
    }
}
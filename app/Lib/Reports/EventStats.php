<?php

namespace App\Lib\Reports;

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

        $rangerSignups = $sql->whereYear('begins', $year)
            ->where('position_id', '!=', Position::ALPHA)
            ->where('position.type', '!=', Position::TYPE_TRAINING)
            ->distinct('person_slot.person_id')
            ->count();

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
                ->whereYear('on_duty', $year)
                ->where('position_id', '!=', Position::ALPHA)
                ->distinct('person_id')
                ->count(),
            // How many people marked on site
            'on_site' => DB::table('person')->where('on_site', true)->count(),

            'alphas_walked' => DB::table('person_mentor')
                ->where('mentor_year', $year)
                ->distinct('person_id')
                ->count(),

            'alphas_passed' => DB::table('person_mentor')
                ->where('mentor_year', $year)
                ->where('status', PersonMentor::PASS)
                ->distinct('person_id')
                ->count(),

            'alphas_bonked' => DB::table('person_mentor')
                ->where('mentor_year', $year)
                ->whereNotIn('status', [PersonMentor::PASS, PersonMentor::PENDING])
                ->distinct('person_id')
                ->count(),

            'alphas_walking' => DB::table('person_mentor')
                ->where('mentor_year', $year)
                ->where('status', PersonMentor::PENDING)
                ->distinct('person_id')
                ->count(),

            // How many people signed up to work
            'working_people_estimate' => DB::table('slot')
                ->join('position', 'slot.position_id', 'position.id')
                ->join('person_slot', 'person_slot.slot_id', 'slot.id')
                ->whereYear('begins', $year)
                ->where('position_id', '!=', Position::ALPHA)
                ->where('position.type', '!=', Position::TYPE_TRAINING)
                ->distinct('person_slot.person_id')
                ->count(),

            'rangers_working_estimate' => $rangerSignups,
        ];
    }
}
<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Position;
use App\Models\Timesheet;
use Illuminate\Support\Facades\DB;

class PotentialSwagReport
{
    /**
     * Retrieve folks who potentially might earn swag (pins & patches)
     *
     * @return array
     */

    public static function execute(): array
    {
        $rows = DB::table('person')
            ->select('id', 'callsign', 'status', 'first_name', 'last_name')
            ->where('status', Person::ACTIVE_STATUSES)
            ->orderBy('callsign')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $year = current_year();
        $ids = $rows->pluck('id');
        $yearsById = Timesheet::yearsRangeredCountForIds($ids);

        $yearsWorked = DB::table('timesheet')
            ->select('person_id', DB::raw('YEAR(on_duty) as last_year'))
            ->whereIntegerInRaw('person_id', $ids)
            ->whereNotIn('position_id', [Position::ALPHA, Position::TRAINING])
            ->orderByRaw('YEAR(on_duty)')
            ->groupBy(DB::raw('person_id, YEAR(on_duty)'))
            ->get()
            ->groupBy('person_id');

        $signedUp = DB::table('person_slot')
            ->select('person_slot.person_id')
            ->join('slot', 'person_slot.slot_id', 'slot.id')
            ->join('position', 'position.id', 'slot.position_id')
            ->whereIntegerInRaw('person_slot.person_id', $ids)
            ->whereYear('slot.begins', $year)
            ->where('position.type', '!=', Position::TYPE_TRAINING)
            ->groupBy('person_slot.person_id')
            ->get()
            ->keyBy('person_id');

        $people = [];
        foreach ($rows as $person) {
            if (preg_match('/(^(ROC Monitor|testing|lam #|temp \d+))|\(test\)/i', $person->callsign)) {
                continue;
            }
            $personId = $person->id;
            $years = $yearsById[$personId] ?? 0;

            $worked = $yearsWorked->get($personId);
            $potentialYears = $years + 1;
            if (($potentialYears % 5) == 0) {
                $swag = "{$potentialYears}-year service";
            } else {
                $swag = '';
            }

            $people[] = [
                'id' => $personId,
                'callsign' => $person->callsign,
                'status' => $person->status,
                'total_years' => $years,
                'first_year' => $worked ? $worked->first()->last_year : '',
                'last_worked' => $worked ? $worked->last()->last_year : '',
                'signed_up' => $signedUp->has($personId),
                'service_eligible' => $swag,
            ];
        }

        return [
            'people' => $people,
            'signup_year' => $year,
        ];
    }
}
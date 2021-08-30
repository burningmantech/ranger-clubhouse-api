<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Position;
use App\Models\Slot;
use App\Models\Timesheet;
use App\Models\Training;
use Exception;
use Illuminate\Support\Facades\DB;

class OnDutyShiftLeadReport
{
    const NON_DIRT = 'non-dirt';
    const COMMAND = 'command';
    const DIRT_AND_GREEN_DOT = 'dirt+green';

    /**
     * Run the ON DUTY Shift Lead report. Find on duty Rangers in positions of interest and show them to Khaki.
     * Include a person's positions which might help Khaki out in a bind.
     *
     * @return array
     * @throws Exception
     */

    public static function execute(): array
    {
        list ($totalGreenDots, $femaleGreenDots) = self::countGreenDotsOnDuty();

        $positions = [];

        return [
            'now' => (string)now(),
            // Positions and head counts
            'below_min_positions' => self::retrievePositionsOnDuty($positions),

            // People signed up
            'non_dirt_signups' => self::retrieveRangersOnDuty(self::NON_DIRT, $positions),
            'command_staff_signups' => self::retrieveRangersOnDuty(self::COMMAND, $positions),
            'dirt_signups' => self::retrieveRangersOnDuty(self::DIRT_AND_GREEN_DOT, $positions),

            // Green Dot head counts
            'green_dot_total' => $totalGreenDots,
            'green_dot_females' => $femaleGreenDots,

            'positions' => $positions,
        ];
    }

    /**
     * Find the people scheduled to work based on $type.
     *
     * @param $positions
     * @return array
     */

    public static function retrievePositionsOnDuty(&$positions): array
    {
        $now = (string)now();
        $rows = Slot::select('slot.*',
            DB::raw('(SELECT COUNT(*) FROM timesheet WHERE timesheet.position_id=slot.position_id AND YEAR(on_duty)=YEAR(begins) AND off_duty is NULL) as on_duty')
        )
            ->join('position', 'position.id', 'slot.position_id')
            ->where('begins', '<=', $now)
            ->where('ends', '>', $now)
            ->with('position')
            ->orderBy('slot.begins')
            ->whereIn('position.type', [Position::TYPE_FRONTLINE, Position::TYPE_COMMAND])
            ->get();


        $results = [];
        foreach ($rows as $row) {
            if ($row->on_duty >= $row->min) {
                continue;
            }

            ShiftLeadReport::addPosition($row->position, $positions);

            $results[] = [
                'slot_id' => $row->id,
                'position_id' => $row->position_id,
                'slot_begins' => (string)$row->begins,
                'slot_ends' => (string)$row->ends,
                'min' => $row->min,
                'max' => $row->max,
                'on_duty' => $row->on_duty,
            ];
        }

        return $results;
    }


    /**
     * Find the people scheduled to work based on $type.
     *
     * @param string $type
     * @param $positions
     * @return array
     */

    public static function retrieveRangersOnDuty(string $type, &$positions): array
    {
        $year = current_year();

        $sql = Timesheet::select(
            'timesheet.position_id',
            'timesheet.on_duty',
            'person.id AS person_id',
            'person.callsign',
            'person.callsign_pronounce',
            'person.gender',
            'person.pronouns',
            'person.pronouns_custom',
            'person.vehicle_blacklisted',
            DB::raw('IFNULL(person_event.signed_motorpool_agreement, FALSE) as signed_motorpool_agreement'),
            DB::raw('IFNULL(person_event.org_vehicle_insurance, FALSE) as org_vehicle_insurance'),
            DB::raw('(SELECT COUNT(DISTINCT YEAR(on_duty)) FROM timesheet WHERE person_id = person.id AND is_non_ranger = false) AS years'),
        )
            ->whereNull('timesheet.off_duty')
            ->whereYear('timesheet.on_duty', $year)
            ->with('position')
            ->join('person', 'person.id', '=', 'timesheet.person_id')
            ->join('position', 'position.id', 'timesheet.position_id')
            ->leftJoin('person_event', function ($j) use ($year) {
                $j->on('person_event.person_id', 'person.id');
                $j->where('person_event.year', $year);
            });

        switch ($type) {
            case self::NON_DIRT:
                $sql->whereNotIn('timesheet.position_id', ShiftLeadReport::DIRT_AND_GREEN_DOT_POSITIONS)
                    ->where('position.type', '=', Position::TYPE_FRONTLINE);
                break;

            case self::COMMAND:
                $sql->where('position.type', Position::TYPE_COMMAND);
                break;

            case self::DIRT_AND_GREEN_DOT:
                $sql->whereIn('timesheet.position_id', ShiftLeadReport::DIRT_AND_GREEN_DOT_POSITIONS)
                    ->orderBy('years', 'desc');
                break;
        }

        $rows = $sql->orderBy('callsign')->get();

        if ($rows->count() == 0) {
            return [];
        }

        $personIds = $rows->pluck('person_id')->toArray();

        $peoplePositions = DB::table('position')
            ->select('person_position.person_id', 'position.short_title', 'position.id as position_id')
            ->join('person_position', 'position.id', 'person_position.position_id')
            ->where('position.on_sl_report', 1)
            ->whereNotIn('position.id', [
                Position::DIRT, Position::DIRT_PRE_EVENT, Position::DIRT_POST_EVENT, Position::DIRT_SHINY_PENNY,
                Position::ONE_GERLACH_PATROL_DIRT
            ])    // Don't need report on dirt
            ->whereIn('person_position.person_id', $personIds)
            ->get()
            ->groupBy('person_id');

        $greenDotTrainingPassed = Training::didIdsPassForYear($personIds, Position::GREEN_DOT_TRAINING, $year);
        $rangers = [];

        foreach ($rows as $row) {
            ShiftLeadReport::addPosition($row->position, $positions);

            $ranger = (object)[
                'id' => $row->person_id,
                'callsign' => $row->callsign,
                'callsign_pronounce' => $row->callsign_pronounce,
                'gender' => Person::summarizeGender($row->gender),
                'pronouns' => $row->pronouns,
                'pronouns_custom' => $row->pronouns_custom,
                'vehicle_blacklisted' => $row->vehicle_blacklisted,
                'signed_motorpool_agreement' => $row->signed_motorpool_agreement,
                'org_vehicle_insurance' => $row->org_vehicle_insurance,
                'years' => $row->years,
                'position_id' => $row->position_id,
                'on_duty' => (string)$row->on_duty,
                'duration' => $row->duration,
            ];
            $rangers[] = $ranger;

            $havePositions = $peoplePositions[$row->person_id] ?? null;

            $positionId = $row->position_id;

            // GD Mentees are not considered to be on a proper GD shift.
            $ranger->is_greendot_shift = (
                $positionId == Position::DIRT_GREEN_DOT
                || $positionId == Position::GREEN_DOT_MENTOR
                || $positionId == Position::ONE_GREEN_DOT
            );

            if (!$havePositions) {
                continue;
            }

            $ranger->is_troubleshooter = $havePositions->whereIn('position_id', [Position::TROUBLESHOOTER, Position::ONE_TROUBLESHOOTER])->count() != 0;
            $ranger->is_rsl = $havePositions->whereIn('position_id', [Position::RSC_SHIFT_LEAD, Position::ONE_SHIFT_LEAD])->count() != 0;
            $ranger->is_ood = $havePositions->contains('position_id', Position::OOD);

            // Determine if the person is a GD AND if they have been trained this year.
            $haveGDPosition = $havePositions->contains(function ($pos) {
                $pid = $pos->position_id;
                return ($pid == Position::DIRT_GREEN_DOT || $pid == Position::GREEN_DOT_MENTOR || $pid == Position::ONE_GREEN_DOT);
            });

            // The check for the mentee shift is a hack to prevent past years from showing
            // a GD Mentee as a qualified GD.
            if ($haveGDPosition) {
                if ($year == 2021) {
                    $ranger->is_greendot = $havePositions->contains('position_id', Position::ONE_GREEN_DOT);
                } else {
                    $ranger->is_greendot = isset($greenDotTrainingPassed[$row->person_id]);
                    if (!$ranger->is_greendot || ($positionId == Position::GREEN_DOT_MENTEE)) {
                        $ranger->is_greendot = false; // just in case
                        // Not trained - remove the GD positions
                        $havePositions = $havePositions->filter(function ($pos) {
                            $pid = $pos->position_id;
                            return ($pid != Position::DIRT_GREEN_DOT && $pid != Position::GREEN_DOT_MENTOR);
                        });
                    }
                }
            }

            $ranger->positions = $havePositions->pluck('short_title')->toArray();
        }

        return $rangers;
    }

    /**
     * Count the GDs current on duty.
     *
     * @return array
     */

    public static function countGreenDotsOnDuty(): array
    {
        $greenDots = DB::table('timesheet')
            ->select('person.id', 'person.gender')
            ->join('person', 'person.id', 'timesheet.person_id')
            ->whereYear('timesheet.on_duty', current_year())
            ->whereNull('timesheet.off_duty')
            ->whereIn('timesheet.position_id', [Position::DIRT_GREEN_DOT, Position::GREEN_DOT_MENTOR, Position::ONE_GREEN_DOT])
            ->get();

        $total = $greenDots->count();
        $females = $greenDots->filter(fn($person) => Person::summarizeGender($person->gender) == 'F')
            ->count();

        return [$total, $females];
    }
}

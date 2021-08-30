<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Position;
use App\Models\Slot;
use App\Models\Training;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShiftLeadReport
{
    const NON_DIRT = 'non-dirt';
    const COMMAND = 'command';
    const DIRT_AND_GREEN_DOT = 'dirt+green';

    const DIRT_AND_GREEN_DOT_POSITIONS = [
        Position::DIRT, Position::DIRT_PRE_EVENT, Position::DIRT_POST_EVENT, Position::DIRT_SHINY_PENNY,
        Position::DIRT_GREEN_DOT, Position::GREEN_DOT_MENTOR, Position::GREEN_DOT_MENTEE,
        Position::ONE_GERLACH_PATROL_DIRT, Position::ONE_GREEN_DOT
    ];

    /**
     * Run the SCHEDULED Shift Lead report Find signups for positions of interest and show them to Khaki.
     * Include a person's positions which might help Khaki out in a bind.
     *
     * @param Carbon $shiftStart - Date time of interested shifts
     * @param int $shiftDuration
     * @return array
     * @throws Exception
     */

    public static function execute(Carbon $shiftStart, int $shiftDuration): array
    {
        $shiftEnd = $shiftStart->clone()->addSeconds($shiftDuration);

        list ($totalGreenDots, $femaleGreenDots) = self::countGreenDotsScheduled($shiftStart, $shiftEnd);

        $positions = [];
        $slots = [];

        return [
            // Positions and head counts
            'incoming_positions' => self::retrievePositionsScheduled($shiftStart, $shiftEnd, false, $positions, $slots),
            'below_min_positions' => self::retrievePositionsScheduled($shiftStart, $shiftEnd, true, $positions, $slots),

            // People signed up
            'non_dirt_signups' => self::retrieveRangersScheduled($shiftStart, $shiftEnd, self::NON_DIRT, $positions, $slots),
            'command_staff_signups' => self::retrieveRangersScheduled($shiftStart, $shiftEnd, self::COMMAND, $positions, $slots),
            'dirt_signups' => self::retrieveRangersScheduled($shiftStart, $shiftEnd, self::DIRT_AND_GREEN_DOT, $positions, $slots),

            // Green Dot head counts
            'green_dot_total' => $totalGreenDots,
            'green_dot_females' => $femaleGreenDots,

            'positions' =>  $positions,
            'slots' => $slots,
        ];
    }

    public static function retrievePositionsScheduled(Carbon $shiftStart, Carbon $shiftEnd, $belowMin, &$positions, &$slots)
    {
        $now = (string)now();
        $sql = Slot::select('slot.*',
            DB::raw('TIMESTAMPDIFF(second,slot.begins,slot.ends) as duration'),
            DB::raw("IF(slot.begins < '$now' AND slot.ends > '$now', TIMESTAMPDIFF(second, '$now', ends),0) as remaining"),
        )->join('position', 'position.id', 'slot.position_id')
            ->with('position')
            ->orderBy('slot.begins');

        self::buildShiftRange($sql, $shiftStart, $shiftEnd, 45);

        if ($belowMin) {
            $sql->whereIn('position.type', [Position::TYPE_FRONTLINE, Position::TYPE_COMMAND]);
            $sql->whereRaw('slot.signed_up < slot.min');
        } else {
            $sql->where('position.type', Position::TYPE_FRONTLINE);
        }

        $rows = $sql->get();

        $slotIds = [];
        foreach ($rows as $row) {
            self::addPosition($row->position, $positions);
            self::addSlot($row, $slots, $shiftStart);
            $slotIds[] = $row->id;
        }

        return $slotIds;
    }

    /**
     * Find the people scheduled to work based on $type.
     *
     * @param Carbon $shiftStart
     * @param Carbon $shiftEnd
     * @param string $type
     * @param $positions
     * @param $slots
     * @return array
     */

    public static function retrieveRangersScheduled(Carbon $shiftStart, Carbon $shiftEnd, string $type, &$positions, &$slots): array
    {
        $year = $shiftStart->year;

        $sql = Slot::select(
            'slot.*',
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
            ->with('position')
            ->join('person_slot', 'person_slot.slot_id', '=', 'slot.id')
            ->join('person', 'person.id', '=', 'person_slot.person_id')
            ->join('position', 'position.id', '=', 'slot.position_id')
            ->leftJoin('person_event', function ($j) use ($year) {
                $j->on('person_event.person_id', 'person.id');
                $j->where('person_event.year', $year);
            })
            ->orderBy('slot.begins');

        // Only report on active positions for the current year. Previous years may reference
        // positions that have been deactivated since.

        if ($shiftStart->year == current_year()) {
            $sql->where('position.active', true);
        }

        self::buildShiftRange($sql, $shiftStart, $shiftEnd, 45);

        switch ($type) {
            case self::NON_DIRT:
                $sql->whereNotIn('slot.position_id', self::DIRT_AND_GREEN_DOT_POSITIONS)
                    ->where('position.type', '=', Position::TYPE_FRONTLINE);
                break;

            case self::COMMAND:
                $sql->where('position.type', Position::TYPE_COMMAND);
                break;

            case self::DIRT_AND_GREEN_DOT:
                $sql->whereIn('slot.position_id', self::DIRT_AND_GREEN_DOT_POSITIONS)
                    ->orderBy('years', 'desc');
                break;
        }

        $sql->orderBy('callsign');

        $rows = $sql->get();

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
            self::addSlot($row, $slots, $shiftStart);
            self::addPosition($row->position, $positions);

            $ranger = (object)[
                'id' => $row->person_id,
                'slot_id' => $row->id,
                'callsign' => $row->callsign,
                'callsign_pronounce' => $row->callsign_pronounce,
                'gender' => Person::summarizeGender($row->gender),
                'pronouns' => $row->pronouns,
                'pronouns_custom' => $row->pronouns_custom,
                'vehicle_blacklisted' => $row->vehicle_blacklisted,
                'signed_motorpool_agreement' => $row->signed_motorpool_agreement,
                'org_vehicle_insurance' => $row->org_vehicle_insurance,
                'years' => $row->years,
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

            if ($havePositions) {
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
        }

        return $rangers;
    }

    /**
     * Count the GDs scheduled to work.
     *
     * @param Carbon $shiftStart
     * @param Carbon $shiftEnd
     * @return array
     */

    public static function countGreenDotsScheduled(Carbon $shiftStart, Carbon $shiftEnd): array
    {
        $sql = DB::table('slot')
            ->select('person.id', 'person.gender')
            ->join('person_slot', 'person_slot.slot_id', 'slot.id')
            ->join('person', 'person.id', 'person_slot.person_id')
            ->whereIn('slot.position_id', [Position::DIRT_GREEN_DOT, Position::GREEN_DOT_MENTOR, Position::ONE_GREEN_DOT]);

        self::buildShiftRange($sql, $shiftStart, $shiftEnd, 90);

        $greenDots = $sql->get();

        $total = $greenDots->count();
        $females = $greenDots->filter(fn($person) => Person::summarizeGender($person->gender) == 'F')
            ->count();

        return [$total, $females];
    }

    /**
     * Find the first slot signed up for by a person and position in a given year
     *
     * @param $sql
     * @param Carbon $shiftStart
     * @param Carbon $shiftEnd
     * @param int $minAfterStart
     */
    public static function buildShiftRange($sql, Carbon $shiftStart, Carbon $shiftEnd, int $minAfterStart)
    {
        $sql->where(function ($q) use ($shiftStart, $shiftEnd, $minAfterStart) {
            // all slots starting before and ending on or start the range
            $q->where([
                ['begins', '<=', $shiftStart],
                ['ends', '>=', $shiftEnd]
            ]);
            // or starting within 1 hour before
            $q->orWhere([
                ['begins', '>=', $shiftStart->clone()->addHours(-1)],
                ['begins', '<', $shiftEnd->clone()->addHours(-1)]
            ]);

            // or.. starting within after X minutes
            $q->orWhere([
                ['ends', '>=', $shiftStart->clone()->addMinutes($minAfterStart)],
                ['ends', '<=', $shiftEnd]
            ]);
        });
    }

    /**
     * Build up the positions seen
     *
     * @param $position
     * @param $positions
     */

    public static function addPosition($position, &$positions)
    {
        $positions[$position->id] ??= [
            'title' => $position->title,
            'short_title' => $position->short_title,
            'type' => $position->type,
            'active' => $position->active,
        ];
    }

    /**
     * Build up the slots seen.
     *
     * @param $slot
     * @param $slots
     * @param Carbon $shiftStart
     */

    public static function addSlot($slot, &$slots, Carbon $shiftStart)
    {
         $slots[$slot->id] ??= [
            'begins' => (string)$slot->begins,
            'ends' => (string)$slot->ends,
            'begins_day_before' => $slot->begins->day != $shiftStart->day,
            'ends_day_after' => $slot->ends->day != $shiftStart->day,
            'description' => $slot->description,
            'signed_up' => $slot->signed_up,
            'position_id' => $slot->position_id,
            'min' => $slot->min,
            'max' => $slot->max,
            'duration' => $slot->duration ?? 0,
            'remaining' => $slot->remaining ?? 0,
        ];
    }
}

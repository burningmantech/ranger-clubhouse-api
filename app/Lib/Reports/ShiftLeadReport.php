<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Position;
use App\Models\Training;

use Carbon\Carbon;

use Illuminate\Support\Facades\DB;

class ShiftLeadReport
{
    const NON_DIRT = 'non-dirt';
    const COMMAND = 'command';
    const DIRT_AND_GREEN_DOT = 'dirt+green';

    public static function execute(Carbon $shiftStart, int $shiftDuration): array
    {
        $shiftEnd = $shiftStart->clone()->addSeconds($shiftDuration);

        return [
            // Positions and head counts
            'incoming_positions' => self::retrievePositionsScheduled($shiftStart, $shiftEnd, false),
            'below_min_positions' => self::retrievePositionsScheduled($shiftStart, $shiftEnd, true),

            // People signed up
            'non_dirt_signups' => self::retrieveRangersScheduled($shiftStart, $shiftEnd, self::NON_DIRT),
            'command_staff_signups' => self::retrieveRangersScheduled($shiftStart, $shiftEnd, self::COMMAND),
            'dirt_signups' => self::retrieveRangersScheduled($shiftStart, $shiftEnd, self::DIRT_AND_GREEN_DOT),

            // Green Dot head counts
            'green_dot_total' => self::countGreenDotsScheduled($shiftStart, $shiftEnd),
            'green_dot_females' => self::countGreenDotsScheduled($shiftStart, $shiftEnd, true),
        ];

    }

    public static function retrievePositionsScheduled(Carbon $shiftStart, Carbon $shiftEnd, $belowMin)
    {
        $now = (string)now();
        $sql = DB::table('slot')
            ->select(
                'slot.begins AS slot_begins',
                'slot.ends AS slot_ends',
                DB::raw('TIMESTAMPDIFF(second,slot.begins,slot.ends) as slot_duration'),
                DB::raw("IF(slot.begins < '$now' AND slot.ends > '$now', TIMESTAMPDIFF(second, '$now', ends),0) as remaining"),
                'slot.description AS description',
                'slot.signed_up AS signed_up',
                'slot.min AS min',
                'slot.max AS max',
                'position.title AS title',
                'position.type AS position_type'
            )->join('position', 'position.id', '=', 'slot.position_id')
            ->orderBy('position.title')
            ->orderBy('slot.begins');

        self::buildShiftRange($sql, $shiftStart, $shiftEnd, 45);

        if ($belowMin) {
            $sql->whereIn('position.type', [Position::TYPE_FRONTLINE, Position::TYPE_COMMAND]);
            $sql->whereRaw('slot.signed_up < slot.min');
        } else {
            $sql->where('position.type', Position::TYPE_FRONTLINE);
        }

        return $sql->get();
    }

    /**
     * Find the people scheduled to work based on $type.
     *
     * @param Carbon $shiftStart
     * @param Carbon $shiftEnd
     * @param string $type
     * @return \Illuminate\Support\Collection
     * @throws \Exception
     */

    public static function retrieveRangersScheduled(Carbon $shiftStart, Carbon $shiftEnd, string $type)
    {
        $year = $shiftStart->year;

        $sql = DB::table('slot')
            ->select(
                'slot.id AS slot_id',
                'slot.begins AS slot_begins',
                'slot.ends AS slot_ends',
                'slot.description',
                'slot.signed_up',
                'person.callsign',
                'person.callsign_pronounce',
                'person.gender',
                'person.pronouns',
                'person.pronouns_custom',
                'person.id AS person_id',
                'person.vehicle_blacklisted',
                DB::raw('IFNULL(person_event.signed_motorpool_agreement, FALSE) as signed_motorpool_agreement'),
                DB::raw('IFNULL(person_event.org_vehicle_insurance, FALSE) as org_vehicle_insurance'),
                'position.title AS position_title',
                'position.short_title AS short_title',
                'position.type AS position_type',
                'position.id AS position_id',
                DB::raw('(SELECT COUNT(DISTINCT YEAR(on_duty)) FROM timesheet WHERE person_id = person.id) AS years')
            )
            ->join('person_slot', 'person_slot.slot_id', '=', 'slot.id')
            ->join('person', 'person.id', '=', 'person_slot.person_id')
            ->join('position', 'position.id', '=', 'slot.position_id')
            ->leftJoin('person_event', function ($j) use ($year) {
                $j->on('person_event.person_id', 'person.id');
                $j->where('person_event.year', $year);
            })
            ->orderBy('slot.begins')
            ->orderByRaw('CASE WHEN position.id=' . Position::DIRT_SHINY_PENNY . ' THEN "1111" ELSE position.title END DESC');

        self::buildShiftRange($sql, $shiftStart, $shiftEnd, 45);

        switch ($type) {
            case self::NON_DIRT:
                $sql->whereNotIn('slot.position_id', [
                    Position::DIRT, Position::DIRT_PRE_EVENT, Position::DIRT_POST_EVENT, Position::DIRT_SHINY_PENNY,
                    Position::DIRT_GREEN_DOT, Position::GREEN_DOT_MENTOR, Position::GREEN_DOT_MENTEE
                ])->where('position.type', '=', Position::TYPE_FRONTLINE);
                break;

            case self::COMMAND:
                $sql->where('position.type', Position::TYPE_COMMAND);
                break;

            case self::DIRT_AND_GREEN_DOT:
                $sql->whereIn('slot.position_id', [
                    Position::DIRT, Position::DIRT_PRE_EVENT, Position::DIRT_POST_EVENT, Position::DIRT_SHINY_PENNY,
                    Position::DIRT_GREEN_DOT, Position::GREEN_DOT_MENTOR, Position::GREEN_DOT_MENTEE
                ])->orderBy('years', 'desc');
                break;
        }

        $sql->orderBy('callsign');

        $people = $sql->get();

        $personIds = $people->pluck('person_id')->toArray();

        $peoplePositions = DB::table('person_position')
            ->select('person_position.person_id', 'position.short_title', 'position.id as position_id')
            ->join('position', 'position.id', '=', 'person_position.position_id')
            ->where('position.on_sl_report', 1)
            ->whereNotIn('position.id', [Position::DIRT, Position::DIRT_PRE_EVENT, Position::DIRT_POST_EVENT, Position::DIRT_SHINY_PENNY])    // Don't need report on dirt
            ->whereIn('person_position.person_id', $personIds)
            ->get()
            ->groupBy('person_id');

        foreach ($people as $person) {
            $person->gender = Person::summarizeGender($person->gender);

            $positions = $peoplePositions[$person->person_id] ?? null;

            $positionId = $person->position_id;

            // GD Mentees are not considered to be on a proper GD shift.
            $person->is_greendot_shift = ($positionId == Position::DIRT_GREEN_DOT
                || $positionId == Position::GREEN_DOT_MENTOR);

            $person->slot_begins_day_before = (new Carbon($person->slot_begins))->day != $shiftStart->day;
            $person->slot_ends_day_after = (new Carbon($person->slot_ends))->day != $shiftStart->day;

            if ($positions) {
                $person->is_troubleshooter = $positions->contains('position_id', Position::TROUBLESHOOTER);
                $person->is_rsl = $positions->contains('position_id', Position::RSC_SHIFT_LEAD);
                $person->is_ood = $positions->contains('position_id', Position::OOD);

                // Determine if the person is a GD AND if they have been trained this year.
                $haveGDPosition = $positions->contains(function ($row) {
                    $pid = $row->position_id;
                    return ($pid == Position::DIRT_GREEN_DOT || $pid == Position::GREEN_DOT_MENTOR);
                });

                // The check for the mentee shift is a hack to prevent past years from showing
                // a GD Mentee as a qualified GD.
                if ($haveGDPosition) {
                    $person->is_greendot = Training::didPersonPassForYear($person->person_id, Position::GREEN_DOT_TRAINING, $year);
                    if (!$person->is_greendot || ($positionId == Position::GREEN_DOT_MENTEE)) {
                        $person->is_greendot = false; // just in case
                        // Not trained - remove the GD positions
                        $positions = $positions->filter(function ($row) {
                            $pid = $row->position_id;
                            return !($pid == Position::DIRT_GREEN_DOT
                                || $pid == Position::GREEN_DOT_MENTOR);
                        });
                    }
                }

                $person->positions = $positions->pluck('short_title')->toArray();
            }
        }

        return $people;
    }

    /**
     * Count the GDs scheduled to work.
     *
     * @param Carbon $shiftStart
     * @param Carbon $shiftEnd
     * @param bool $femaleOnly if true, use the gender field to determine if they might be female.
     * @return int
     */

    public static function countGreenDotsScheduled(Carbon $shiftStart, Carbon $shiftEnd, bool $femaleOnly = false) : int
    {
        $rows = DB::select('SELECT version() as version');
        $useModernRegexp = stripos($rows[0]->version, '8.') === 0;

        $sql = DB::table('slot')
            ->join('person_slot', 'person_slot.slot_id', '=', 'slot.id')
            ->join('person', 'person.id', '=', 'person_slot.person_id')
            ->whereIn('slot.position_id', [Position::DIRT_GREEN_DOT, Position::GREEN_DOT_MENTOR]);

        self::buildShiftRange($sql, $shiftStart, $shiftEnd, 90);

        if ($femaleOnly) {
            $sql->where(function ($q) use ($useModernRegexp) {
                $q->whereRaw('lower(LEFT(person.gender,1)) = "f"');
                if ($useModernRegexp) {
                    // Mysql 8.0 +
                    $q->orWhereRaw('person.gender REGEXP "\\b(female|girl|femme|lady|she|her|woman|famale|fem)\\b"');
                } else {
                    // Mysql < 8.0
                    $q->orWhereRaw('person.gender REGEXP "[[:<:]](female|girl|femme|lady|she|her|woman|famale|fem)[[:>:]]"');
                }
            });
        }

        return $sql->count();
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

}

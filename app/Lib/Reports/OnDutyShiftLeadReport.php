<?php

namespace App\Lib\Reports;

use App\Lib\SummarizeGender;
use App\Models\Position;
use App\Models\Slot;
use App\Models\Timesheet;
use App\Models\Training;
use Exception;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Run the ON DUTY Shift Lead report. Find on-duty Rangers in positions of
 * interest and show them to Khaki, including each person's other positions
 * which might help Khaki out in a bind.
 *
 * The motorpool / insurance / vehicle-blacklist flags are surfaced so Khaki
 * can identify, at a glance, who is eligible (or barred) for motorpool duties
 * while managing the current shift. Access is gated to EVENT_MANAGEMENT.
 */
class OnDutyShiftLeadReport
{
    const string NON_DIRT = 'non-dirt';
    const string COMMAND = 'command';
    const string DIRT_AND_GREEN_DOT = 'dirt+green';

    /**
     * Run the report.
     *
     * @return array<string, mixed>
     * @throws Exception
     */
    public static function execute(): array
    {
        $year = current_year();

        [$totalGreenDots, $femaleGreenDots] = self::countGreenDotsOnDuty($year);

        $positions = [];

        return [
            'now' => (string)now(),

            // Positions and head counts.
            'below_min_positions' => self::retrievePositionsOnDuty($positions),

            // People on duty, grouped by position class.
            'non_dirt_signups' => self::retrieveRangersOnDuty(self::NON_DIRT, $year, $positions),
            'command_staff_signups' => self::retrieveRangersOnDuty(self::COMMAND, $year, $positions),
            'dirt_signups' => self::retrieveRangersOnDuty(self::DIRT_AND_GREEN_DOT, $year, $positions),

            // Green Dot head counts.
            'green_dot_total' => $totalGreenDots,
            'green_dot_females' => $femaleGreenDots,

            // Shared accumulator of every position referenced above, keyed by
            // position id. Populated by side-effect via the helpers above.
            'positions' => $positions,
        ];
    }

    /**
     * Find currently active slots that are below their minimum head count.
     *
     * @param array<int, array<string, mixed>> $positions shared accumulator, mutated by reference
     * @return array<int, array<string, mixed>>
     */
    public static function retrievePositionsOnDuty(array &$positions): array
    {
        $now = (string)now();

        $rows = Slot::select(
            'slot.*',
            DB::raw('(SELECT COUNT(*) FROM timesheet WHERE timesheet.position_id=slot.position_id AND YEAR(on_duty)=YEAR(begins) AND off_duty is NULL) as on_duty')
        )
            ->join('position', 'position.id', 'slot.position_id')
            ->where('begins', '<=', $now)
            ->where('ends', '>', $now)
            ->whereIn('position.type', [Position::TYPE_FRONTLINE, Position::TYPE_COMMAND])
            ->with('position')
            ->orderBy('slot.begins')
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
     * Find the people on duty for the given position class.
     *
     * @param string $type one of self::NON_DIRT, self::COMMAND, self::DIRT_AND_GREEN_DOT
     * @param array<int, array<string, mixed>> $positions shared accumulator, mutated by reference
     * @return array<int, object>
     */
    public static function retrieveRangersOnDuty(string $type, int $year, array &$positions): array
    {
        $rows = self::queryRangersOnDuty($type, $year)->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $personIds = $rows->pluck('person_id')->toArray();
        $peoplePositions = self::loadPersonPositions($personIds);
        $greenDotTrainingPassed = Training::didIdsPassForYear($personIds, Position::GREEN_DOT_TRAINING, $year);

        $rangers = [];
        foreach ($rows as $row) {
            ShiftLeadReport::addPosition($row->position, $positions);

            $rangers[] = self::buildRanger(
                $row,
                $peoplePositions[$row->person_id] ?? null,
                isset($greenDotTrainingPassed[$row->person_id])
            );
        }

        return $rangers;
    }

    /**
     * Build the on-duty people query, including the type-specific filter.
     */
    protected static function queryRangersOnDuty(string $type, int $year): EloquentBuilder
    {
        $query = Timesheet::select(
            'timesheet.position_id',
            'timesheet.on_duty',
            'person.id AS person_id',
            'person.callsign',
            'person.callsign_pronounce',
            'person.gender_identity',
            'person.gender_custom',
            'person.pronouns',
            'person.pronouns_custom',
            'person.vehicle_blacklisted',
            DB::raw('IFNULL(person_event.signed_motorpool_agreement, FALSE) as signed_motorpool_agreement'),
            DB::raw('IFNULL(person_event.org_vehicle_insurance, FALSE) as org_vehicle_insurance'),
            DB::raw('(SELECT COUNT(DISTINCT YEAR(on_duty)) FROM timesheet WHERE person_id = person.id AND is_echelon = false) AS years'),
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

        match ($type) {
            self::NON_DIRT => $query
                ->whereNotIn('timesheet.position_id', ShiftLeadReport::DIRT_AND_GREEN_DOT_POSITIONS)
                ->where('position.type', '=', Position::TYPE_FRONTLINE),
            self::COMMAND => $query
                ->where('position.type', Position::TYPE_COMMAND),
            self::DIRT_AND_GREEN_DOT => $query
                ->whereIn('timesheet.position_id', ShiftLeadReport::DIRT_AND_GREEN_DOT_POSITIONS)
                ->orderBy('years', 'desc'),
        };

        return $query->orderBy('callsign');
    }

    /**
     * Load the SL-relevant positions held by each person, keyed by person_id.
     *
     * @param array<int, int> $personIds
     */
    protected static function loadPersonPositions(array $personIds): Collection
    {
        return DB::table('position')
            ->select('person_position.person_id', 'position.short_title', 'position.id as position_id')
            ->join('person_position', 'position.id', 'person_position.position_id')
            ->where('position.on_sl_report', 1)
            ->whereNotIn('position.id', ShiftLeadReport::DIRT_POSITIONS) // Don't need a report on dirt
            ->whereIntegerInRaw('person_position.person_id', $personIds)
            ->get()
            ->groupBy('person_id');
    }

    /**
     * Assemble the per-person ranger record returned to the report.
     *
     * Every property is initialized here so the returned shape is consistent
     * regardless of whether the person holds any SL-relevant positions.
     *
     * The `duration` value is the live elapsed-time accessor on the Timesheet
     * model: because these rows are all currently on duty (off_duty IS NULL),
     * it reflects seconds elapsed from on_duty to "now" at access time.
     */
    protected static function buildRanger(Timesheet $row, ?Collection $havePositions, bool $passedGreenDotTraining): object
    {
        $positionId = $row->position_id;

        $ranger = (object)[
            'id' => $row->person_id,
            'callsign' => $row->callsign,
            'callsign_pronounce' => $row->callsign_pronounce,
            'gender' => SummarizeGender::parse($row->gender_identity, $row->gender_custom),
            'on_site' => true, // Any person on duty is considered to be on site.
            'pronouns' => $row->pronouns,
            'pronouns_custom' => $row->pronouns_custom,
            'vehicle_blacklisted' => $row->vehicle_blacklisted,
            'signed_motorpool_agreement' => $row->signed_motorpool_agreement,
            'org_vehicle_insurance' => $row->org_vehicle_insurance,
            'years' => $row->years,
            'position_id' => $positionId,
            'on_duty' => (string)$row->on_duty,
            'duration' => $row->duration,
            // GD Mentees are not considered to be on a proper GD shift.
            'is_greendot_shift' => in_array($positionId, ShiftLeadReport::GREEN_DOT_SHIFT_POSITIONS),
            'is_troubleshooter' => false,
            'is_rsl' => false,
            'is_ood' => false,
            'is_greendot' => false,
            'positions' => [],
        ];

        if ($havePositions) {
            $ranger->is_troubleshooter = $havePositions->contains('position_id', Position::TROUBLESHOOTER);
            $ranger->is_rsl = $havePositions->contains('position_id', Position::RSC_SHIFT_LEAD);
            $ranger->is_ood = $havePositions->contains('position_id', Position::OOD);

            $havePositions = self::resolveGreenDotStatus($ranger, $havePositions, $positionId, $passedGreenDotTraining);
            $ranger->positions = $havePositions->pluck('short_title')->toArray();
        }

        return $ranger;
    }

    /**
     * Decide whether this person counts as a qualified GD, mutating $ranger and
     * stripping GD positions when they do not qualify.
     */
    protected static function resolveGreenDotStatus(object $ranger, Collection $havePositions, int $positionId, bool $passedGreenDotTraining): Collection
    {
        $haveGDPosition = $havePositions->contains(
            fn($pos) => in_array($pos->position_id, ShiftLeadReport::GREEN_DOT_SHIFT_POSITIONS)
        );

        if (!$haveGDPosition) {
            return $havePositions;
        }

        // The check against the mentee shift prevents past years from showing
        // a GD Mentee as a qualified GD.
        $ranger->is_greendot = $passedGreenDotTraining && $positionId !== Position::GREEN_DOT_MENTEE;

        if (!$ranger->is_greendot) {
            // Not a qualified GD - drop the GD positions from their list.
            return $havePositions->filter(
                fn($pos) => !in_array($pos->position_id, ShiftLeadReport::GREEN_DOT_SHIFT_POSITIONS)
            );
        }

        return $havePositions;
    }

    /**
     * Count the GDs currently on duty.
     *
     * @return array{0: int, 1: int} [total, female count]
     */
    public static function countGreenDotsOnDuty(int $year): array
    {
        $greenDots = DB::table('timesheet')
            ->select('person.id', 'person.gender_identity', 'person.gender_custom')
            ->join('person', 'person.id', 'timesheet.person_id')
            ->whereYear('timesheet.on_duty', $year)
            ->whereNull('timesheet.off_duty')
            ->whereIn('timesheet.position_id', ShiftLeadReport::GREEN_DOT_SHIFT_POSITIONS)
            ->get();

        $total = $greenDots->count();
        $females = $greenDots
            ->filter(fn($p) => SummarizeGender::parse($p->gender_identity, $p->gender_custom) == SummarizeGender::FEMALE)
            ->count();

        return [$total, $females];
    }
}

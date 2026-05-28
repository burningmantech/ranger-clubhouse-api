<?php

namespace App\Lib\Reports;

use App\Lib\SummarizeGender;
use App\Models\Certification;
use App\Models\PersonCertification;
use App\Models\Position;
use App\Models\Slot;
use App\Models\Training;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShiftLeadReport
{
    const string NON_DIRT = 'non-dirt';
    const string COMMAND = 'command';
    const string DIRT_AND_GREEN_DOT = 'dirt+green';

    /**
     * Minutes a slot must extend past $shiftStart to be considered overlapping
     * for position/people queries.
     */
    const int POSITION_OVERLAP_MIN = 45;

    /**
     * Minutes a slot must extend past $shiftStart to be considered overlapping
     * for the Green Dot headcount.
     */
    const int GREEN_DOT_OVERLAP_MIN = 90;

    const array GREEN_DOT_SHIFT_POSITIONS = [
        Position::DIRT_GREEN_DOT, Position::GREEN_DOT_MENTOR,
    ];

    const array DIRT_POSITIONS = [
        Position::DIRT, Position::DIRT_PRE_EVENT, Position::DIRT_POST_EVENT, Position::DIRT_SHINY_PENNY,
    ];

    const array DIRT_AND_GREEN_DOT_POSITIONS = [
        ...self::DIRT_POSITIONS,
        Position::DIRT_GREEN_DOT, Position::GREEN_DOT_MENTOR, Position::GREEN_DOT_MENTEE,
    ];

    /**
     * Run the SCHEDULED Shift Lead report. Find signups for positions of
     * interest and show them to Khaki, including a person's other positions
     * which might help Khaki out in a bind.
     *
     * @throws Exception
     */
    public static function execute(Carbon $shiftStart, int $shiftDuration): array
    {
        $shiftEnd = $shiftStart->clone()->addSeconds($shiftDuration);

        [$totalGreenDots, $femaleGreenDots] = self::countGreenDotsScheduled($shiftStart, $shiftEnd);

        $positions = [];
        $slots = [];

        $certifications = Certification::where('on_sl_report', true)->get()->keyBy('id');

        return [
            'incoming_positions' => self::retrievePositionsScheduled($shiftStart, $shiftEnd, false, $positions, $slots),
            'below_min_positions' => self::retrievePositionsScheduled($shiftStart, $shiftEnd, true, $positions, $slots),

            'non_dirt_signups' => self::retrievePeopleScheduled($shiftStart, $shiftEnd, self::NON_DIRT, $positions, $slots, $certifications),
            'command_staff_signups' => self::retrievePeopleScheduled($shiftStart, $shiftEnd, self::COMMAND, $positions, $slots, $certifications),
            'dirt_signups' => self::retrievePeopleScheduled($shiftStart, $shiftEnd, self::DIRT_AND_GREEN_DOT, $positions, $slots, $certifications),

            'green_dot_total' => $totalGreenDots,
            'green_dot_females' => $femaleGreenDots,

            'positions' => $positions,
            'slots' => $slots,
        ];
    }

    public static function retrievePositionsScheduled(Carbon $shiftStart, Carbon $shiftEnd, bool $belowMin, array &$positions, array &$slots): array
    {
        $query = Slot::select('slot.*')
            ->join('position', 'position.id', 'slot.position_id')
            ->with('position')
            ->where('position.active', true)
            ->orderBy('slot.begins');

        self::buildShiftRange($query, $shiftStart, $shiftEnd, self::POSITION_OVERLAP_MIN);

        if ($belowMin) {
            $query->whereIn('position.type', [Position::TYPE_FRONTLINE, Position::TYPE_COMMAND])
                ->whereRaw('slot.signed_up < slot.min');
        } else {
            $query->where('position.type', Position::TYPE_FRONTLINE);
        }

        $slotIds = [];
        foreach ($query->get() as $row) {
            self::addPosition($row->position, $positions);
            self::addSlot($row, $slots, $shiftStart);
            $slotIds[] = $row->id;
        }

        return $slotIds;
    }

    /**
     * Find the people scheduled to work based on $type.
     */
    public static function retrievePeopleScheduled(
        Carbon     $shiftStart,
        Carbon     $shiftEnd,
        string     $type,
        array      &$positions,
        array      &$slots,
        Collection $certifications
    ): array {
        $year = $shiftStart->year;
        $isCurrentYear = ($year === current_year());

        $rows = self::queryPeopleScheduled($shiftStart, $shiftEnd, $type, $year, $isCurrentYear)->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $personIds = $rows->pluck('person_id')->toArray();
        $peoplePositions = self::loadPersonPositions($personIds, $isCurrentYear);
        $peopleCertifications = self::loadPersonCertifications($personIds, $certifications);
        $greenDotTrainingPassed = Training::didIdsPassForYear($personIds, Position::GREEN_DOT_TRAINING, $year);

        $rangers = [];
        foreach ($rows as $row) {
            self::addSlot($row, $slots, $shiftStart);
            self::addPosition($row->position, $positions);

            $rangers[] = self::buildRanger(
                $row,
                $peoplePositions[$row->person_id] ?? null,
                $peopleCertifications->get($row->person_id),
                $certifications,
                isset($greenDotTrainingPassed[$row->person_id])
            );
        }

        return $rangers;
    }

    /**
     * Build the main people-scheduled query, including the type-specific filter.
     */
    protected static function queryPeopleScheduled(Carbon $shiftStart, Carbon $shiftEnd, string $type, int $year, bool $isCurrentYear): EloquentBuilder
    {
        $query = Slot::select(
            'slot.*',
            'person.id AS person_id',
            'person.callsign',
            'person.callsign_pronounce',
            'person.on_site',
            'person.gender_identity',
            'person.gender_custom',
            'person.pronouns',
            'person.pronouns_custom',
            'person.vehicle_blacklisted',
            DB::raw('IFNULL(person_event.signed_motorpool_agreement, FALSE) as signed_motorpool_agreement'),
            DB::raw('IFNULL(person_event.org_vehicle_insurance, FALSE) as org_vehicle_insurance'),
            DB::raw('COALESCE(JSON_LENGTH(person.years_as_ranger), 0) as years'),
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

        // Previous years may reference positions that have since been deactivated.
        if ($isCurrentYear) {
            $query->where('position.active', true);
        }

        self::buildShiftRange($query, $shiftStart, $shiftEnd, self::POSITION_OVERLAP_MIN);

        match ($type) {
            self::NON_DIRT => $query
                ->whereNotIn('slot.position_id', self::DIRT_AND_GREEN_DOT_POSITIONS)
                ->where('position.type', '=', Position::TYPE_FRONTLINE),
            self::COMMAND => $query
                ->where('position.type', Position::TYPE_COMMAND),
            self::DIRT_AND_GREEN_DOT => $query
                ->whereIn('slot.position_id', self::DIRT_AND_GREEN_DOT_POSITIONS)
                ->orderBy('years', 'desc'),
        };

        $query->orderBy('callsign');

        return $query;
    }

    /**
     * Load the SL-relevant positions held by each person, keyed by person_id.
     */
    protected static function loadPersonPositions(array $personIds, bool $isCurrentYear): Collection
    {
        $query = DB::table('position')
            ->select('person_position.person_id', 'position.short_title', 'position.id as position_id')
            ->join('person_position', 'position.id', 'person_position.position_id')
            ->where('position.on_sl_report', 1)
            ->whereNotIn('position.id', self::DIRT_POSITIONS) // Don't need a report on dirt
            ->whereIn('person_position.person_id', $personIds);

        if ($isCurrentYear) {
            $query->where('position.active', true);
        }

        return $query->get()->groupBy('person_id');
    }

    /**
     * Load the SL-relevant certifications held by each person, keyed by person_id.
     */
    protected static function loadPersonCertifications(array $personIds, Collection $certifications): Collection
    {
        if ($certifications->isEmpty()) {
            return collect([]);
        }

        return PersonCertification::whereIn('certification_id', $certifications->keys())
            ->whereIntegerInRaw('person_id', $personIds)
            ->get()
            ->groupBy('person_id');
    }

    /**
     * Assemble the per-person ranger record returned to the SL report.
     */
    protected static function buildRanger(
        $row,
        ?Collection $havePositions,
        ?Collection $personCerts,
        Collection $certificationsById,
        bool $passedGreenDotTraining
    ): object {
        $positionId = $row->position_id;

        $ranger = (object)[
            'id' => $row->person_id,
            'slot_id' => $row->id,
            'callsign' => $row->callsign,
            'callsign_pronounce' => $row->callsign_pronounce,
            'gender' => SummarizeGender::parse($row->gender_identity, $row->gender_custom),
            'pronouns' => $row->pronouns,
            'pronouns_custom' => $row->pronouns_custom,
            'on_site' => $row->on_site,
            'vehicle_blacklisted' => $row->vehicle_blacklisted,
            'signed_motorpool_agreement' => $row->signed_motorpool_agreement,
            'org_vehicle_insurance' => $row->org_vehicle_insurance,
            'years' => (int)$row->years,
            'certifications' => self::formatCertifications($personCerts, $certificationsById),
            // GD Mentees are not considered to be on a proper GD shift.
            'is_greendot_shift' => in_array($positionId, self::GREEN_DOT_SHIFT_POSITIONS),
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
     * Sort the SL titles for the certifications a person holds.
     *
     * @return string[]
     */
    protected static function formatCertifications(?Collection $personCerts, Collection $certificationsById): array
    {
        if (!$personCerts) {
            return [];
        }

        $titles = $personCerts->map(function ($pc) use ($certificationsById) {
            $cert = $certificationsById[$pc->certification_id];
            return $cert->sl_title ?? $cert->title;
        })->all();

        usort($titles, 'strcasecmp');
        return $titles;
    }

    /**
     * Decide whether this slot counts as a qualified GD, mutating $ranger and
     * stripping GD positions when the ranger does not qualify.
     */
    protected static function resolveGreenDotStatus(object $ranger, Collection $havePositions, int $positionId, bool $passedGreenDotTraining): Collection
    {
        $haveGDPosition = $havePositions->contains(fn($pos) => in_array($pos->position_id, self::GREEN_DOT_SHIFT_POSITIONS));
        if (!$haveGDPosition) {
            return $havePositions;
        }

        // The check against the mentee shift prevents past years from showing
        // a GD Mentee as a qualified GD.
        $ranger->is_greendot = $passedGreenDotTraining && $positionId !== Position::GREEN_DOT_MENTEE;

        if (!$ranger->is_greendot) {
            // Not a qualified GD on this slot - drop the GD positions from their list
            return $havePositions->filter(fn($pos) => !in_array($pos->position_id, self::GREEN_DOT_SHIFT_POSITIONS));
        }

        return $havePositions;
    }

    /**
     * Count the GDs scheduled to work.
     */
    public static function countGreenDotsScheduled(Carbon $shiftStart, Carbon $shiftEnd): array
    {
        $query = DB::table('slot')
            ->select('person.id', 'person.gender_identity', 'person.gender_custom')
            ->join('person_slot', 'person_slot.slot_id', 'slot.id')
            ->join('person', 'person.id', 'person_slot.person_id')
            ->whereIn('slot.position_id', self::GREEN_DOT_SHIFT_POSITIONS);

        self::buildShiftRange($query, $shiftStart, $shiftEnd, self::GREEN_DOT_OVERLAP_MIN);

        $greenDots = $query->get();

        $total = $greenDots->count();
        $females = $greenDots
            ->filter(fn($p) => SummarizeGender::parse($p->gender_identity, $p->gender_custom) == SummarizeGender::FEMALE)
            ->count();

        return [$total, $females];
    }

    /**
     * Constrain a slot query to slots overlapping the report window. A slot is
     * considered overlapping if any of the following hold:
     *   - it fully spans the window (begins before, ends at/after)
     *   - it begins within the hour leading up to $shiftStart (and not in the
     *     final hour of the window)
     *   - it ends inside the window, at least $minAfterStart minutes past
     *     $shiftStart
     */
    public static function buildShiftRange(EloquentBuilder|QueryBuilder $query, Carbon $shiftStart, Carbon $shiftEnd, int $minAfterStart): void
    {
        $query->where(function ($q) use ($shiftStart, $shiftEnd, $minAfterStart) {
            // Slot fully spans the window.
            $q->where([
                ['begins', '<=', $shiftStart],
                ['ends', '>=', $shiftEnd],
            ]);

            // Slot begins in the hour before $shiftStart (and not within the
            // last hour of the window).
            $q->orWhere(function ($q) use ($shiftStart, $shiftEnd) {
                $q->where('begins', '>=', $shiftStart->clone()->subHour())
                    ->where('begins', '<', $shiftEnd->clone()->subHour());
            });

            // Slot ends inside the window, at least $minAfterStart minutes past start.
            $q->orWhere(function ($q) use ($shiftStart, $shiftEnd, $minAfterStart) {
                $q->where('ends', '>=', $shiftStart->clone()->addMinutes($minAfterStart))
                    ->where('ends', '<=', $shiftEnd);
            });
        });
    }

    /**
     * Record a position in the shared positions accumulator.
     */
    public static function addPosition($position, array &$positions): void
    {
        $positions[$position->id] ??= [
            'title' => $position->title,
            'short_title' => $position->short_title,
            'type' => $position->type,
            'active' => $position->active,
        ];
    }

    /**
     * Record a slot in the shared slots accumulator.
     */
    public static function addSlot($slot, array &$slots, Carbon $shiftStart): void
    {
        $slots[$slot->id] ??= [
            'begins' => (string)$slot->begins,
            'ends' => (string)$slot->ends,
            'begins_day_before' => !$slot->begins->isSameDay($shiftStart),
            'ends_day_after' => !$slot->ends->isSameDay($shiftStart),
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

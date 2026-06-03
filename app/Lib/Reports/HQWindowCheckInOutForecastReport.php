<?php

namespace App\Lib\Reports;

use App\Models\Position;
use App\Models\Slot;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Builds the HQ Window check-in/check-out staffing forecast for a year.
 *
 * The report buckets slot begin/end times into fixed intervals and counts the
 * expected number of people checking in/out at HQ, plus the number of HQ
 * Window/Runner/Short/Lead staff on duty, per interval. It also produces a
 * parallel set of "burn" forecasts keyed by Burn Perimeter shift start times.
 *
 * Output shape:
 *   [
 *     'visits' => list<PeriodCounts as array + 'period' => string>,
 *     'burns'  => list<['descriptions' => string[], 'visits' => list<...>]>,
 *   ]
 */
class HQWindowCheckInOutForecastReport
{
    private const int DEFAULT_INTERVAL_MINUTES = 15;

    /** Positions whose begin/end times define the HQ open window and staffing. */
    private const array HQ_POSITIONS = [
        Position::HQ_WINDOW,
        Position::HQ_RUNNER,
        Position::HQ_SHORT,
        Position::HQ_LEAD,
    ];

    /** Positions excluded from generic check-in/check-out visit counts. */
    private const array TRAINING_POSITIONS = [
        Position::TRAINING,
        Position::TRAINER,
        Position::TRAINER_UBER,
        Position::TRAINER_ASSOCIATE,
    ];

    /**
     * Map of staffing-count period key => HQ position id that fills it.
     *
     * Single source of truth for the staffing columns: used to build the empty
     * period template, drive the per-position staffing queries, and copy
     * staffing counts into burn-perimeter visits.
     *
     * @var array<string, int>
     */
    private const array STAFFING_COLUMNS = [
        'windows' => Position::HQ_WINDOW,
        'runners' => Position::HQ_RUNNER,
        'shorts' => Position::HQ_SHORT,
        'leads' => Position::HQ_LEAD,
    ];

    /**
     * @return array{visits: list<array<string, int|string>>, burns: list<array{descriptions: list<string>, visits: list<array<string, int|string>>}>}
     */
    public static function execute(int $year, int $interval = self::DEFAULT_INTERVAL_MINUTES): array
    {
        $intervalSeconds = self::intervalSeconds($interval);

        $window = self::findHqWindow($year, $intervalSeconds);
        if ($window === null) {
            return ['visits' => [], 'burns' => []];
        }

        [$hqStart, $hqEnd] = $window;

        $allVisits = self::findAllHQVisits($hqStart, $hqEnd, $intervalSeconds);

        return [
            'visits' => self::keyedPeriodsToList(self::trimTrailingEmptyPeriods($allVisits)),
            'burns' => self::collectBurnPerimeterGroups($year, $allVisits, $intervalSeconds),
        ];
    }

    /**
     * Validate the interval and convert it to seconds.
     *
     * Guards against the division-by-zero / zero-length DateInterval that an
     * interval of 0 (or negative) would otherwise trigger in the SQL bucket
     * math and the DatePeriod construction.
     */
    private static function intervalSeconds(int $intervalMinutes): int
    {
        if ($intervalMinutes < 1 || $intervalMinutes > 1440) {
            throw new InvalidArgumentException('interval must be between 1 and 1440 minutes');
        }

        return $intervalMinutes * 60;
    }

    /**
     * Determine the bucketed open/close boundaries of HQ for the year.
     *
     * @return array{0: string, 1: string}|null  [opening, closing] or null when no HQ slots exist
     */
    private static function findHqWindow(int $year, int $intervalSeconds): ?array
    {
        $opening = self::slotQuery()
            ->selectRaw(self::bucketExpr('begins') . ' as opening', [$intervalSeconds, $intervalSeconds])
            ->whereIn('position_id', self::HQ_POSITIONS)
            ->where('begins_year', $year)
            ->orderBy('begins')
            ->first();

        if ($opening === null) {
            return null;
        }

        $roundUp = $intervalSeconds - 1;

        $closing = self::slotQuery()
            ->selectRaw(self::bucketExpr('ends', roundUp: true) . ' as closing', [$intervalSeconds, $roundUp, $intervalSeconds])
            ->whereIn('position_id', self::HQ_POSITIONS)
            ->where('begins_year', $year)
            ->orderBy('ends', 'desc')
            ->first();

        if ($closing === null) {
            return null;
        }

        return [(string) $opening->opening, (string) $closing->closing];
    }

    /**
     * Build the per-interval forecast for all HQ visits within [start, end].
     *
     * Associative array; key is the period 'YYYY-mm-dd HH:MM:00', value is an
     * empty-period array (see {@see self::emptyPeriod()}) with counts filled in:
     *  'checkin'  - expected check-in visits
     *  'checkout' - expected check-out visits
     *  'windows'  - HQ Window workers on duty
     *  'runners'  - HQ Runners on duty
     *  'shorts'   - HQ Shorts on duty
     *  'leads'    - HQ Leads on duty
     *
     * @return array<string, array<string, int>>
     */
    private static function findAllHQVisits(string $start, string $end, int $intervalSeconds): array
    {
        $periods = self::buildPeriodSkeleton($start, $end, $intervalSeconds);
        if ($periods === []) {
            return [];
        }

        // Generic check-ins/check-outs: everything except HQ and training positions.
        $visitFilter = static function (Builder $query) use ($start, $end): void {
            $query->whereNotIn('slot.position_id', self::HQ_POSITIONS)
                ->whereNotIn('slot.position_id', self::TRAINING_POSITIONS)
                ->whereRaw('begins >= CAST(? AS DATETIME)', [$start])
                ->whereRaw('ends <= CAST(? AS DATETIME)', [$end]);
        };

        self::populateForecastColumn(
            self::findHQVisits('begins', $visitFilter, $intervalSeconds),
            $periods,
            'checkin'
        );
        self::populateForecastColumn(
            self::findHQVisits('ends', $visitFilter, $intervalSeconds),
            $periods,
            'checkout'
        );

        // Per-position staffing: running on-duty headcount across the day.
        foreach (self::STAFFING_COLUMNS as $column => $positionId) {
            $staffFilter = static function (Builder $query) use ($positionId, $start, $end): void {
                $query->where('slot.position_id', $positionId)
                    ->where(static function (Builder $inner) use ($start, $end): void {
                        $inner->whereRaw('begins BETWEEN CAST(? AS DATETIME) AND CAST(? AS DATETIME)', [$start, $end])
                            ->orWhereRaw('ends BETWEEN CAST(? AS DATETIME) AND CAST(? AS DATETIME)', [$start, $end]);
                    });
            };

            self::populateForecastColumn(
                self::findHQVisits('begins', $staffFilter, $intervalSeconds),
                $periods,
                $column
            );
            self::populateForecastShiftEnd(
                self::findHQVisits('ends', $staffFilter, $intervalSeconds),
                $periods,
                $column
            );
        }

        return $periods;
    }

    /**
     * Collect Burn Perimeter forecast groups keyed by distinct shift start time.
     *
     * @param array<string, array<string, int>> $allVisits
     * @return list<array{descriptions: list<string>, visits: list<array<string, int|string>>}>
     */
    private static function collectBurnPerimeterGroups(int $year, array $allVisits, int $intervalSeconds): array
    {
        $burnPerimeterSlots = Slot::query()
            ->without(['position', 'trainer_slot'])
            ->select(['begins', 'description'])
            ->where('position_id', Position::BURN_PERIMETER)
            ->where('begins_year', $year)
            ->orderBy('begins')
            ->get();

        $burnsByTime = [];

        foreach ($burnPerimeterSlots as $slot) {
            $begins = (string) $slot->begins;

            // PERF TODO: findBurnPerimeterVisits issues 2 queries per distinct
            // begins-time (N+1 across the year). The buckets could be derived
            // from $burnPerimeterSlots in PHP, but the SQL floor-bucketing on
            // unix_timestamp is non-trivial to reproduce byte-for-byte, so we
            // keep the query path to guarantee identical output.
            if (!array_key_exists($begins, $burnsByTime)) {
                $burnsByTime[$begins] = [
                    'descriptions' => [],
                    'visits' => self::findBurnPerimeterVisits($begins, $allVisits, $intervalSeconds),
                ];
            }

            $burnsByTime[$begins]['descriptions'][] = $slot->description;
        }

        return array_values($burnsByTime);
    }

    /**
     * Find the check-in/check-out forecast for all Burn Perimeter slots that
     * start at a given time, overlaying HQ staffing counts from $existingVisits.
     *
     * @param array<string, array<string, int>> $existingVisits keyed forecast from findAllHQVisits()
     * @return list<array<string, int|string>>
     */
    private static function findBurnPerimeterVisits(string $start, array $existingVisits, int $intervalSeconds): array
    {
        $filter = static function (Builder $query) use ($start): void {
            $query->where('position_id', Position::BURN_PERIMETER)
                ->where('begins', $start);
        };

        $checkins = self::findHQVisits('begins', $filter, $intervalSeconds);
        $checkouts = self::findHQVisits('ends', $filter, $intervalSeconds);

        $visits = [];
        self::mergeBurnVisits($visits, $checkins, 'checkin', $existingVisits);
        self::mergeBurnVisits($visits, $checkouts, 'checkout', $existingVisits);

        $results = [];
        foreach ($visits as $period => $visit) {
            $visit['period'] = $period;
            $results[] = $visit;
        }

        return $results;
    }

    /**
     * Merge one direction (check-in or check-out) of burn-perimeter totals into
     * the keyed $visits accumulator, copying HQ staffing counts from the
     * matching period in $existingVisits when present.
     *
     * @param array<string, array<string, int>> $visits accumulator, modified in place
     * @param Collection<int, object{time: string, total: int|string|null}> $rows
     * @param array<string, array<string, int>> $existingVisits
     */
    private static function mergeBurnVisits(array &$visits, Collection $rows, string $column, array $existingVisits): void
    {
        foreach ($rows as $row) {
            $time = (string) $row->time;

            $period = $visits[$time] ?? self::emptyPeriod();
            $period[$column] = (int) $row->total;

            if (isset($existingVisits[$time])) {
                foreach (array_keys(self::STAFFING_COLUMNS) as $staffColumn) {
                    $period[$staffColumn] = (int) $existingVisits[$time][$staffColumn];
                }
            }

            $visits[$time] = $period;
        }
    }

    /**
     * Run the bucketed sum(signed_up) query for one column (begins or ends).
     *
     * @param 'begins'|'ends' $column
     * @param callable(Builder): void $filter applies the row-selection predicate (with bindings)
     * @return Collection<int, object{time: string, total: int|string|null}>
     */
    private static function findHQVisits(string $column, callable $filter, int $intervalSeconds): Collection
    {
        $query = self::slotQuery()
            ->selectRaw(self::bucketExpr($column) . " as `time`", [$intervalSeconds, $intervalSeconds])
            ->selectRaw('sum(signed_up) as total');

        $filter($query);

        return $query->groupBy('time')
            ->orderBy('time')
            ->get();
    }

    /**
     * Set $column to the bucketed total for each matching period.
     *
     * @param Collection<int, object{time: string, total: int|string|null}> $rows
     * @param array<string, array<string, int>> $periods modified in place
     */
    private static function populateForecastColumn(Collection $rows, array &$periods, string $column): void
    {
        foreach ($rows as $row) {
            $time = (string) $row->time;
            if (array_key_exists($time, $periods)) {
                $periods[$time][$column] = (int) $row->total;
            }
        }
    }

    /**
     * Accumulate a running on-duty headcount for $column: each period adds the
     * shift-starts already stored in $column and subtracts the shift-ends in
     * $rows, carrying the total forward chronologically.
     *
     * @param Collection<int, object{time: string, total: int|string|null}> $rows shift-end totals
     * @param array<string, array<string, int>> $periods modified in place
     */
    private static function populateForecastShiftEnd(Collection $rows, array &$periods, string $column): void
    {
        $subtract = [];
        foreach ($rows as $row) {
            $time = (string) $row->time;
            if (array_key_exists($time, $periods)) {
                $subtract[$time] = (int) $row->total;
            }
        }

        $total = 0;
        foreach ($periods as $time => $period) {
            $total += $period[$column];
            if (isset($subtract[$time])) {
                $total -= $subtract[$time];
            }

            $periods[$time][$column] = $total;
        }
    }

    /**
     * Build the empty per-interval skeleton between $start and $end inclusive.
     *
     * @return array<string, array<string, int>>
     */
    private static function buildPeriodSkeleton(string $start, string $end, int $intervalSeconds): array
    {
        $dtStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $start);
        $dtEnd = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $end);

        if ($dtStart === false || $dtEnd === false) {
            return [];
        }

        $intervalMinutes = intdiv($intervalSeconds, 60);
        $interval = DateInterval::createFromDateString("$intervalMinutes minutes");

        // INCLUDE_END_DATE so a checkout that buckets onto the closing boundary
        // (when the latest shift ends exactly on an interval boundary) is not
        // silently dropped by DatePeriod's normally-exclusive end.
        $period = new DatePeriod($dtStart, $interval, $dtEnd, DatePeriod::INCLUDE_END_DATE);

        $periods = [];
        foreach ($period as $dt) {
            $periods[$dt->format('Y-m-d H:i:00')] = self::emptyPeriod();
        }

        return $periods;
    }

    /**
     * Drop the closing-boundary period when it carries no data.
     *
     * buildPeriodSkeleton includes the closing boundary (via INCLUDE_END_DATE)
     * so a boundary-aligned final checkout is not lost. But $hqEnd is rounded up
     * past the data in the common case, so that single extra period would be
     * emitted empty on every report. This removes only that one boundary period,
     * and only when empty — legitimate earlier all-zero periods are preserved.
     *
     * @param array<string, array<string, int>> $periods
     * @return array<string, array<string, int>>
     */
    private static function trimTrailingEmptyPeriods(array $periods): array
    {
        if ($periods === []) {
            return $periods;
        }

        foreach (end($periods) as $count) {
            if ($count !== 0) {
                return $periods;
            }
        }

        array_pop($periods);

        return $periods;
    }

    /**
     * Flatten a keyed period map into an indexed list, folding the key into a
     * 'period' entry on each row.
     *
     * @param array<string, array<string, int>> $periods
     * @return list<array<string, int|string>>
     */
    private static function keyedPeriodsToList(array $periods): array
    {
        $list = [];
        foreach ($periods as $period => $counts) {
            $counts['period'] = $period;
            $list[] = $counts;
        }

        return $list;
    }

    /**
     * Single source of truth for a zeroed period row.
     *
     * @return array<string, int>
     */
    private static function emptyPeriod(): array
    {
        return [
            'checkin' => 0,
            'checkout' => 0,
            ...array_fill_keys(array_keys(self::STAFFING_COLUMNS), 0),
        ];
    }

    /**
     * The bucket-flooring SQL expression for a datetime column.
     *
     * Emits placeholders (?) for the interval seconds so callers bind values
     * rather than interpolating them. With $roundUp the expression rounds the
     * timestamp up to the end of its bucket (used for closing boundaries).
     */
    private static function bucketExpr(string $column, bool $roundUp = false): string
    {
        if ($roundUp) {
            return "FROM_UNIXTIME(? * floor((unix_timestamp($column) + ?) / ?))";
        }

        return "FROM_UNIXTIME(? * floor(unix_timestamp($column) / ?))";
    }

    private static function slotQuery(): Builder
    {
        return Slot::query()->getQuery();
    }
}

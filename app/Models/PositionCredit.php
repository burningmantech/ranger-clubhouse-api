<?php

namespace App\Models;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\InvalidArgumentException;

class PositionCredit extends ApiModel
{
    protected $table = 'position_credit';
    protected bool $auditModel = true;

    protected $fillable = [
        'credits_per_hour',
        'description',
        'end_time',
        'position_id',
        'start_time',
    ];

    protected $casts = [
        'credits_per_hour' => 'float',
        'end_time' => 'datetime',
        'start_time' => 'datetime',
    ];

    protected $rules = [
        'start_time' => 'required|date',
        'end_time' => 'required|date|after:start_time',
        'position_id' => 'required|exists:position,id',
        'description' => 'required|string',
        'credits_per_hour' => 'required|numeric',
    ];

    protected $virtualColumns = [
        'start_year',
        'end_year'
    ];

    const RELATIONS = ['position:id,title'];

    public int $start_timestamp;
    public int $end_timestamp;

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Find all credits for a given year
     *
     * @param int $year
     * @return Collection
     */

    public static function findForYear(int $year): Collection
    {
        return self::with(self::RELATIONS)
            ->where('start_year', $year)
            ->orderBy('start_time')
            ->get();
    }

    public function loadRelations(): void
    {
        $this->load(self::RELATIONS);
    }

    /**
     * Find all the credits for a given year and position, cache the results.
     *
     * @param int $year
     * @param int $positionId
     * @return mixed
     * @throws InvalidArgumentException
     */

    public static function findForYearPosition(int $year, int $positionId): mixed
    {
        $cacheKey = self::getCacheKey($year);
        $cached = self::cacheStore()->get($cacheKey) ?? [];
        if (isset($cached[$positionId])) {
            return $cached[$positionId];
        }

        $rows = self::where('position_id', $positionId)
            ->where('start_year', $year)
            ->where('end_year', $year)
            ->orderBy('start_time')
            ->get();

        foreach ($rows as $row) {
            // Cache the timestamp conversion
            $row->start_timestamp = $row->start_time->timestamp;
            $row->end_timestamp = $row->end_time->timestamp;
        }

        $cached[$positionId] = $rows;
        self::cacheStore()->put($cacheKey, $cached);

        return $rows;
    }

    /**
     * Warm the position credit cache with credits based on the given year and position ids.
     * A performance optimization to help computeCredits() avoid extra lookups.
     *
     * @param int $year
     * @param $positionIds
     */

    public static function warmYearCache(int $year, $positionIds): void
    {
        self::warmBulkYearCache([$year => $positionIds]);
    }

    /**
     * Warm the position credit cache with credits based on the given year and position ids.
     *
     * @param $bulkYears
     * @throws InvalidArgumentException
     */

    public static function warmBulkYearCache($bulkYears): void
    {
        $sql = self::query();

        $cacheStore = self::cacheStore();
        $didCache = true;
        foreach ($bulkYears as $year => $positionIds) {
            $cacheKey = self::getCacheKey($year);
            if (empty($positionIds)) {
                $sql->orWhere('start_year', $year);
                $didCache = false;
                $cacheStore->put($cacheKey, []); // Pulling in all positional credits for the year.
                continue;
            }

            $yearCache = $cacheStore->get($cacheKey) ?? [];
            $findIds = [];
            foreach ($positionIds as $id) {
                if (!isset($yearCache[$id])) {
                    $findIds[] = $id;
                    $yearCache[$year][$id] = [];
                }
            }

            if (empty($findIds)) {
                // Cache already warmed for this year & positions.
                continue;
            }

            $didCache = false;
            $sql->orWhere(function ($q) use ($year, $findIds) {
                $q->where('start_year', $year);
                $q->whereIn('position_id', $findIds);
            });
        }

        if ($didCache) {
            // Cache was already warmed.
            return;
        }

        $pcByYear = $sql->orderBy('start_time')
            ->get()
            ->groupBy(fn($row) => $row->start_year);

        foreach ($pcByYear as $year => $rows) {
            $cacheKey = self::getCacheKey($year);
            $yearCache = $cacheStore->get($cacheKey) ?? [];
            foreach ($rows as $row) {
                // Cache the timestamp conversion
                $row->start_timestamp = $row->start_time->timestamp;
                $row->end_timestamp = $row->end_time->timestamp;
                $yearCache[$row->position_id][] = $row;
            }
            $cacheStore->put($cacheKey, $yearCache);
        }
    }

    public static function cacheStore(): Repository
    {
        return Cache::store('array');
    }

    public static function getCacheKey(int $year): string
    {
        return 'pc-' . $year;
    }

    /**
     * Compute the credits for a position given the start and end times
     *
     * @param int $positionId the id of the position
     * @param int $startTime the starting time of the shift
     * @param int $endTime the ending time of the shift
     * @return float earned credits
     * @throws InvalidArgumentException
     */

    public static function computeCredits(int $positionId, int $startTime, int $endTime, int $year): float
    {
        $credits = PositionCredit::findForYearPosition($year, $positionId);

        if (empty($credits)) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($credits as $credit) {
            $minutes = self::minutesOverlap($startTime, $endTime, $credit->start_timestamp, $credit->end_timestamp);

            if ($minutes > 0) {
                $total += $minutes * $credit->credits_per_hour / 60.0;
            }
        }

        return $total;
    }

    /**
     * Bulk compute the credits. This avoids the overhead with hitting the cache over, and over when iterating over
     * a large slot/timesheet set and magic accessor methods (e.g., $slot->credits)
     *
     * Note: the cache must be warmed before running this.
     *
     * @param $rows array of objects with properties position_id,  & credits
     * @param int $year the year being computed
     * @param string $startColumn the start timestamp (integer) column name in the $rows
     * @param string $endColumn the end timestamp (integer) column name in the $rows
     * @throws InvalidArgumentException
     */

    public static function bulkComputeCredits($rows, int $year, string $startColumn, string $endColumn): void
    {
        $cacheKey = self::getCacheKey($year);
        $cached = self::cacheStore()->get($cacheKey) ?? [];

        foreach ($rows as $row) {
            if (isset($cached[$row->position_id])) {
                $credits = $cached[$row->position_id];
                if (empty($credits)) {
                    $row->credits = 0.0;
                    continue;
                }
            } else {
                $row->credits = 0.0;
                continue;
            }

            $total = 0.0;

            foreach ($credits as $credit) {
                $minutes = self::minutesOverlap($row->{$startColumn}, $row->{$endColumn}, $credit->start_timestamp, $credit->end_timestamp);

                if ($minutes > 0) {
                    $total += $minutes * $credit->credits_per_hour / 60.0;
                }
            }

            $row->credits = $total;
        }
    }

    public static function minutesOverlap(int $startA, int $endA, int $startB, int $endB): float
    {
        // latest start time
        $start = max($startA, $startB);
        // earliest end time
        $ending = min($endA, $endB);

        if ($start >= $ending) {
            return 0; # no overlap
        }

        return round(($ending - $start) / 60.0);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionCredit extends ApiModel
{
    protected $table = 'position_credit';
    protected $auditModel = true;

    protected $fillable = [
        'credits_per_hour',
        'description',
        'end_time',
        'position_id',
        'start_time',
    ];

    protected $casts = [
        'credits_per_hour' => 'float'
    ];

    protected $dates = [
        'start_time',
        'end_time'
    ];

    protected $rules = [
        'start_time' => 'required|date',
        'end_time' => 'required|date|after:start_time',
        'position_id' => 'required|exists:position,id',
        'description' => 'required|string',
        'credits_per_hour' => 'required|numeric',
    ];

    const RELATIONS = ['position:id,title'];

    public static array $yearCache = [];

    public int $start_timestamp;
    public int $end_timestamp;

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public static function clearCache()
    {
        self::$yearCache = [];
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
            ->whereYear('start_time', $year)
            ->orderBy('start_time')->get();
    }

    public function loadRelations()
    {
        $this->load(self::RELATIONS);
    }

    /**
     * Find all the credits for a given year and position, cache the results.
     *
     * @param int $year
     * @param int $positionId
     * @return mixed
     */

    public static function findForYearPosition(int $year, int $positionId): mixed
    {
        if (isset(self::$yearCache[$year][$positionId])) {
            return self::$yearCache[$year][$positionId];
        }

        $rows = self::where('position_id', $positionId)
            ->whereYear('start_time', $year)
            ->whereYear('end_time', $year)
            ->orderBy('start_time')
            ->get();

        foreach ($rows as $row) {
            // Cache the timestamp conversion
            $row->start_timestamp = $row->start_time->timestamp;
            $row->end_timestamp = $row->end_time->timestamp;
        }

        self::$yearCache[$year][$positionId] = $rows;

        return $rows;
    }

    /**
     * Warm the position credit cache with credits based on the given year and position ids.
     * A performance optimization to help computeCredits() avoid extra lookups.
     *
     * @param int $year
     * @param $positionIds
     */

    /**
     * Warm the position credit cache with credits based on the given year and position ids.
     * A performance optimization to help computeCredits() avoid extra lookups.
     *
     * @param int $year
     * @param $positionIds
     */

    public static function warmYearCache(int $year, $positionIds)
    {
        self::warmBulkYearCache([$year => $positionIds]);
    }

    /**
     * Warm the position credit cache with credits based on the given year and position ids.
     *
     * @param $bulkYears
     */

    public static function warmBulkYearCache($bulkYears)
    {
        $sql = self::query();

        $didCache = true;
        foreach ($bulkYears as $year => $positionIds) {
            if (empty($positionIds)) {
                $sql->orWhereYear('start_time', $year);
                $didCache = false;
                self::$yearCache[$year] = []; // Pulling in all positional credits for the year.
                continue;
            }

            $findIds = [];
            foreach ($positionIds as $id) {
                if (!isset(self::$yearCache[$year][$id])) {
                    $findIds[] = $id;
                    self::$yearCache[$year][$id] = [];
                }
            }

            if (empty($findIds)) {
                // Cache already warmed for this year & positions.
                continue;
            }

            $didCache = false;
            $sql->orWhere(function ($q) use ($year, $findIds) {
                $q->whereYear('start_time', $year);
                $q->whereIn('position_id', $findIds);
            });
        }

        if ($didCache) {
            // Cache was already warmed.
            return;
        }

        $rows = $sql->orderBy('start_time')->get();

        foreach ($rows as $row) {
            // Cache the timestamp conversion
            $row->start_timestamp = $row->start_time->timestamp;
            $row->end_timestamp = $row->end_time->timestamp;
            self::$yearCache[$row->start_time->year][$row->position_id][] = $row;
        }
    }

    /**
     * Compute the credits for a position given the start and end times
     *
     * @param int $positionId the id of the position
     * @param int $startTime the starting time of the shift
     * @param int $endTime the ending time of the shift
     * @return float earned credits
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

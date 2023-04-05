<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonStatus extends ApiModel
{
    protected $table = 'person_status';

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime'
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function person_source(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Record the change in a person's status.
     *
     * @param int $personId person to track
     * @param string|null $oldStatus old status
     * @param string $newStatus new status
     * @param string|null $reason reason it changed
     * @param int|null $personSourceId user who made the change
     */

    public static function record(int $personId, ?string $oldStatus, string $newStatus, ?string $reason, ?int $personSourceId): void
    {
        self::create([
            'person_id' => $personId,
            'person_source_id' => $personSourceId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason
        ]);
    }

    /**
     * Retrieve the status history for a person
     *
     * @param $personId
     * @return Collection
     */

    public static function retrieveAllForId($personId): Collection
    {
        return self::where('person_id', $personId)
            ->with('person_source:id,callsign')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Find the PersonStatus record that exists on or before the time given.
     * Used to determine a person's status at a given point in time.
     *
     * @param int $personId
     * @param string|Carbon $time
     * @return ?PersonStatus
     */

    public static function findForTime(int $personId, string|Carbon $time): ?PersonStatus
    {
        return self::where('person_id', $personId)
            ->where('created_at', '<=', $time)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Find the PersonStatus record that exists on or before the time given.
     * Used to determine a person's status at a given point in time.
     *
     * @param array $personIds
     * @param string|Carbon $time
     * @return Collection
     */

    public static function findStatusForIdsTime(array $personIds, string|Carbon $time): Collection
    {
        return self::whereIntegerInRaw('person_id', $personIds)
            ->where('created_at', '<=', $time)
            ->whereRaw("created_at = (SELECT ps.created_at FROM person_status ps WHERE ps.person_id=person_status.person_id AND ps.created_at <= ? ORDER BY ps.created_at DESC LIMIT 1)", [(string)$time])
            ->get()
            ->keyBy('person_id');
    }

}

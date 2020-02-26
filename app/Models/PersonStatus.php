<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\ApiModel;

use App\Models\Person;

class PersonStatus extends ApiModel
{
    protected $table = 'person_status';

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime'
    ];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function person_source()
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Record the change in a person's status.
     *
     * @param $personId person to track
     * @param $oldStatus old status
     * @param $newStatus new status
     * @param $reason reason it changed
     * @param $personSourceId user who made the change
     */

    public static function record($personId, $oldStatus, $newStatus, $reason, $personSourceId)
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

    public static function retrieveAllForId($personId)
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
     * @return PersonStatus
     */

    public static function findForTime($personId, $time)
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
     * @param array $personId
     * @param string|Carbon $time
     * @return Collection
     */

    public static function findStatusForIdsTime($personIds, $time)
    {
        return self::whereIn('person_id', $personIds)
            ->where('created_at', '<=', $time)
            ->whereRaw("created_at = (SELECT ps.created_at FROM person_status ps WHERE ps.person_id=person_status.person_id AND ps.created_at <= ? ORDER BY ps.created_at DESC LIMIT 1)", [(string)$time])
            ->get()
            ->keyBy('person_id');
    }

}

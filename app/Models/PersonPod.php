<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Attack of the Pod People!
 */
class PersonPod extends ApiModel
{
    use HasFactory;

    public $table = 'person_pod';
    public bool $auditModel = true;
    public $timestamps = true;

    public $guarded = [];   // Not directly accessible via APIs

    public $casts = [
        'is_lead' => 'bool',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',      // Time person left the pod at due to shift end.
        'removed_at' => 'datetime',   // Time person was manually removed from the pod.
    ];

    protected $attributes = [
        'is_lead' => false
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function pod(): BelongsTo
    {
        return $this->belongsTo(Pod::class);
    }

    public static function boot(): void
    {
        parent::boot();

        self::creating(function ($model) {
            $model->joined_at = now();
        });
    }

    /**
     * Find a current person in a pod
     *
     * @param int $personId
     * @param int $podId
     * @return PersonPod|null
     */

    public static function findCurrentPersonPod(int $personId, int $podId): ?PersonPod
    {
        return self::where('person_id', $personId)
            ->where('pod_id', $podId)
            ->whereNull('removed_at')
            ->first();
    }

    /**
     * Find a current membership record for the given person and timesheet
     *
     * @param int $personId
     * @param int $timesheetId
     * @return PersonPod|null
     */


    public static function findByPersonTimesheet(int $personId, int $timesheetId): ?PersonPod
    {
        return self::where('person_id', $personId)
            ->where('timesheet_id', $timesheetId)
            ->whereNull('left_at')
            ->whereNull('removed_at')
            ->first();
    }

    /**
     * How many current members are in the pod?
     *
     * @param $podId
     * @return int
     */

    public static function currentMemberCount($podId): int
    {
        return self::where('pod_id', $podId)->whereNull('removed_at')->count();
    }
}

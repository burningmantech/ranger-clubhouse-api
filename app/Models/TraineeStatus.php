<?php

namespace App\Models;

use App\Models\ApiModel;

class TraineeStatus extends ApiModel
{
    protected $table = 'trainee_status';

    protected $fillable = [
        'person_id',
        'slot_id',
        'notes',
        'rank',
        'passed',
    ];

    protected $casts = [
        'passed'    => 'boolean',
        'begins'    => 'date'
    ];

    /*
     * Find trainee_status records with joined slot for a person & year.
     * (Note: record returned will be a merged trainee_state & slot row.
     */

    public static function findForPersonYear($personId, $year)
    {
        return self::join('slot', 'slot.id', 'trainee_status.slot_id')
                ->where('person_id', $personId)
                ->whereYear('slot.begins', $year)
                ->orderBy('slot.begins')
                ->get();

    }

    public static function didPersonPassForYear($personId, $positionId, $year) {
        return self::join('slot', 'slot.id', 'trainee_status.slot_id')
                ->where('trainee_status.person_id', $personId)
                ->where('slot.position_id', $positionId)
                ->whereYear('slot.begins', $year)
                ->where('passed', 1)
                ->exists();
    }

    public static function firstOrCreateForSession($personId, $sessionId) {
        return self::firstOrCreate([ 'person_id' => $personId, 'slot_id' => $sessionId]);
    }

    public function setRankAttribute($value) {
        $this->attributes['rank'] = empty($value) ? null : $value;
    }
}

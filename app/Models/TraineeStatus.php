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
        'begins'    => 'date',
        'ends'  => 'date'
    ];

    /**
     * Find trainee_status records with joined slot for a person & year.
     * (Note: record returned will be a merged trainee_state & slot row.
     *
     * @param integer $personId person id to find
     * @param integer $year year to look up
     * @return Illuminate\Database\Eloquent\Collection
     */

    public static function findForPersonYear($personId, $year)
    {
        // Find the first training that passed
        return self::join('slot', 'slot.id', 'trainee_status.slot_id')
                // Ensure the person is actually signed up
                ->join('person_slot', function ($q) use ($personId)  {
                    $q->on('person_slot.slot_id', 'trainee_status.slot_id');
                    $q->where('person_slot.person_id', $personId);
                })
                ->where('trainee_status.person_id', $personId)
                ->whereYear('slot.begins', $year)
                ->orderBy('trainee_status.passed', 'asc')
                ->orderBy('slot.begins')
                ->get();

    }

    public static function didPersonPassForYear($personId, $positionId, $year) {
        $positionIds = [ $positionId ];

        if ($positionId == Position::HQ_FULL_TRAINING) {
            $positionIds[] = Position::HQ_REFRESHER_TRAINING;
        }

        return self::join('slot', 'slot.id', 'trainee_status.slot_id')
                ->where('trainee_status.person_id', $personId)
                ->whereIn('slot.position_id', $positionIds)
                ->whereYear('slot.begins', $year)
                ->where('passed', 1)
                ->exists();
    }

    public static function firstOrNewForSession($personId, $sessionId) {
        return self::firstOrNew([ 'person_id' => $personId, 'slot_id' => $sessionId]);
    }

    /*
     * Delete all records refering to a slot. Used by slot deletion.
     */

    public static function deleteForSlot($slotId) {
        self::where('slot_id', $slotId)->delete();
    }

    public function setRankAttribute($value) {
        $this->attributes['rank'] = empty($value) ? null : $value;
    }
}

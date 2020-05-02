<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Models\Person;
use App\Models\Slot;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
        'passed' => 'boolean',
        'begins' => 'date',
        'ends' => 'date'
    ];

    public function person() {
        return $this->belongsTo(Person::class);
    }

    public function slot() {
        return $this->belongsTo(Slot::class);
    }

    /**
     * Find trainee_status records with joined slot for a person & year.
     * (Note: record returned will be a merged trainee_state & slot row.
     *
     * @param integer $personId person id to find
     * @param integer $year year to look up
     * @param integer $positionId
     * @return TraineeStatus[]|Collection
     */

    public static function findForPersonYear(int $personId, int $year, ?int $positionId = null)
    {
        // Find the first training that passed
        $sql = self::join('slot', 'slot.id', 'trainee_status.slot_id')
            // Ensure the person is actually signed up
            ->join('person_slot', function ($q) use ($personId) {
                $q->on('person_slot.slot_id', 'trainee_status.slot_id');
                $q->where('person_slot.person_id', $personId);
            })
            ->where('trainee_status.person_id', $personId)
            ->whereYear('slot.begins', $year)
            ->orderBy('trainee_status.passed', 'asc')
            ->orderBy('slot.begins');

        if ($positionId) {
            $sql->where('slot.position_id', $positionId);
        }

        return $sql->get();

    }

    /**
     * Did the person pass training in a given year?
     * @param int $personId
     * @param int $positionId
     * @param int $year
     * @return bool
     */

    public static function didPersonPassForYear(int $personId, int $positionId, int $year) : bool
    {
        $positionIds = [$positionId];

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

    public static function firstOrNewForSession($personId, $sessionId)
    {
        return self::firstOrNew(['person_id' => $personId, 'slot_id' => $sessionId]);
    }

    /**
     * Did the person pass a specific training?
     *
     * @param int $personId
     * @param int $slotId
     * @return bool
     */

    public static function didPersonPassSession(int $personId, int $slotId) : bool {
        return self::where('person_id', $personId)
                    ->where('passed', true)
                    ->where('slot_id', $slotId)
                    ->exists();
    }

    /**
     * Delete all records refering to a slot. Used by slot deletion.
     * @param int $slotId
     */

    public static function deleteForSlot(int $slotId) : void
    {
        self::where('slot_id', $slotId)->delete();
    }

    public function setRankAttribute($value)
    {
        $this->attributes['rank'] = empty($value) ? null : $value;
    }
}

<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Models\Slot;
use App\Models\Person;

use Illuminate\Support\Facades\DB;

class TrainerStatus extends ApiModel
{
    protected $table = 'trainer_status';
    public $timestamps = true;

    const ATTENDED = 'attended';
    const PENDING = 'pending';
    const NO_SHOW = 'no-show';

    protected $guarded = [];

    public function slot() {
        return $this->belongsTo(Slot::class);
    }

    public function trainer_slot() {
        return $this->belongsTo(Slot::class);
    }

    public function person() {
        return $this->belongsTo(Person::class);
    }

    /*
      * trainer_slot_id refers to the Trainer sign up (Trainer / Trainer Associate / Trainer Uber /etc)
      * slot_id refers to the training session (trainee)
      */

    public static function firstOrNewForSession($sessionId, $personId) {
        return self::firstOrNew([ 'person_id' => $personId, 'slot_id' => $sessionId]);
    }

    public static function findBySlotPersonIds($slotId, $personIds) {
        return self::where('slot_id', $slotId)->whereIn('person_id', $personIds)->get();
    }

    /**
     * Did a person teach a session?
     *
     * @param integer $personId the person to query
     * @param integer $positionId the position (Training / Green Dot Training / etc) to see if they taught
     * @param integer $year the year to check
     * @return bool return true if the person taught
     */

    public static function didPersonTeachForYear($personId, $positionId, $year) {
        $positionIds = Position::TRAINERS[$positionId] ?? null;
        if (!$positionIds) {
            return false;
        }

        return self::join('slot', 'slot.id', 'trainer_status.slot_id')
                ->where('trainer_status.person_id', $personId)
                ->whereIn('slot.position_id', $positionIds)
                ->whereYear('slot.begins', $year)
                ->where('status', self::ATTENDED)
                ->exists();
    }

    /**
     * Retrieve all the sessions the person may have taught
     *
     * @param int $personId the person to check
     * @param array $positionIds the positions to check (Trainer / Trainer Assoc. / Uber /etc)
     * @param int $year the year to check
     * @return \Illuminate\Support\Collection
     */

    public static function retrieveSessionsForPerson(int $personId, $positionIds, int $year)
    {
        return DB::table('slot')
                ->select('slot.id', 'slot.begins', 'slot.ends', 'slot.description', 'slot.position_id', 'trainer_status.status')
                ->join('person_slot', function ($q) use ($personId)  {
                    $q->on('person_slot.slot_id', 'slot.id');
                    $q->where('person_slot.person_id', $personId);
                })
                ->leftJoin('trainer_status', function ($q) use ($personId) {
                    $q->on('trainer_status.trainer_slot_id', 'slot.id');
                    $q->where('trainer_status.person_id', $personId);
                })
                ->whereYear('slot.begins', $year)
                ->whereIn('position_id', $positionIds)
                ->orderBy('slot.begins')
                ->get();
    }

    /*
     * Delete all records refering to a slot. Used by slot deletion.
     */

    public static function deleteForSlot($slotId) {
        self::where('slot_id', $slotId)->delete();
        self::where('trainer_slot_id', $slotId)->delete();
    }
}

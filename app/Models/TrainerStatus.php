<?php

namespace App\Models;

use App\Lib\AwardManagement;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrainerStatus extends ApiModel
{
    protected $table = 'trainer_status';
    protected bool $auditModel = true;
    public $timestamps = true;

    const string ATTENDED = 'attended';
    const string PENDING = 'pending';
    const string NO_SHOW = 'no-show';

    protected $guarded = ['id'];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function trainer_slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public static function boot(): void
    {
        parent::boot();

        self::saved(function ($model) {
            if ($model->trainer_slot?->full_position?->awards_eligible) {
                AwardManagement::rebuildPerson($model->person_id);
            }
        });

        self::deleted(function ($model) {
            if ($model->trainer_slot?->full_position?->awards_eligible) {
                AwardManagement::rebuildPerson($model->person_id);
            }
        });
    }

    /**
     * trainer_slot_id refers to the Trainer sign up (Trainer / Trainer Associate / Trainer Uber /etc)
     * slot_id refers to the training session (trainee)
     *
     * @param int $sessionId
     * @param int $personId
     * @return ?TrainerStatus
     */

    public static function firstOrNewForSession(int $sessionId, int $personId): ?TrainerStatus
    {
        return self::firstOrNew(['person_id' => $personId, 'slot_id' => $sessionId]);
    }

    public static function findBySlotPersonIds(int $slotId, $personIds): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('slot_id', $slotId)->whereIntegerInRaw('person_id', $personIds)->get();
    }

    /**
     * Did a person teach a session?
     *
     * @param int $personId the person to query
     * @param int $positionId the position (Training / Green Dot Training / etc) to see if they taught
     * @param int $year the year to check
     * @return bool return true if the person taught
     */

    public static function didPersonTeachForYear(int $personId, int $positionId, int $year): bool
    {
        $positionIds = Position::TRAINERS[$positionId] ?? null;
        if (!$positionIds) {
            return false;
        }

        return DB::table('slot')
            ->join('trainer_status', 'slot.id', 'trainer_status.trainer_slot_id')
            ->where('slot.begins_year', $year)
            ->whereIn('slot.position_id', $positionIds)
            ->where('trainer_status.person_id', $personId)
            ->where('trainer_status.status', self::ATTENDED)
            ->where('slot.active', true)
            ->exists();
    }

    /**
     * Retrieve all the sessions the person may have taught
     *
     * @param int $personId the person to check
     * @param array $positionIds positions to check (Trainer / Trainer Assoc. / Uber /etc)
     * @param int $year the year to check
     * @return Collection
     */

    public static function retrieveSessionsForPerson(int $personId, array $positionIds, int $year): Collection
    {
        return DB::table('slot')
            ->select('slot.id', 'slot.begins', 'slot.ends', 'slot.description', 'slot.timezone_abbr', 'slot.position_id', DB::raw('IFNULL(trainer_status.status, "pending") as status'))
            ->join('person_slot', function ($q) use ($personId) {
                $q->on('person_slot.slot_id', 'slot.id');
                $q->where('person_slot.person_id', $personId);
            })
            ->leftJoin('trainer_status', function ($q) use ($personId) {
                $q->on('trainer_status.trainer_slot_id', 'slot.id');
                $q->where('trainer_status.person_id', $personId);
            })
            ->where('slot.active', true)
            ->where('slot.begins_year', $year)
            ->whereIn('position_id', $positionIds)
            ->orderBy('slot.begins')
            ->get();
    }

    /**
     * Delete all records referring to a slot. Used by slot deletion.
     *
     * @param int $slotId
     */

    public static function deleteForSlot(int $slotId): void
    {
        self::where('slot_id', $slotId)->delete();
        self::where('trainer_slot_id', $slotId)->delete();
    }
}

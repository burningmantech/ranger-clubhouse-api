<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraineeStatus extends ApiModel
{
    protected $table = 'trainee_status';
    protected $auditModel = true;

    protected $fillable = [
        'person_id',
        'slot_id',
        'notes',
        'rank',
        'passed',
    ];

    protected $casts = [
        'passed' => 'boolean',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    /**
     * Find trainee_status records with joined slot for a person & year.
     * (Note: record returned will be a merged trainee_state & slot row.
     *
     * @param int $personId person id to find
     * @param int $year year to look up
     * @param ?int $positionId
     * @return Collection
     */

    public static function findForPersonYear(int $personId, int $year, ?int $positionId = null): Collection
    {
        // Find the first training that passed
        $sql = self::join('slot', 'slot.id', 'trainee_status.slot_id')
            // Ensure the person is actually signed up
            ->join('person_slot', function ($q) use ($personId) {
                $q->on('person_slot.slot_id', 'trainee_status.slot_id');
                $q->where('person_slot.person_id', $personId);
            })
            ->whereYear('slot.begins', $year)
            ->where('slot.active', true)
            ->where('trainee_status.person_id', $personId)
            ->orderBy('trainee_status.passed')
            ->orderBy('slot.begins');

        if ($positionId) {
            $ids = [$positionId];
            if ($positionId == Position::HQ_FULL_TRAINING) {
                $ids[] = Position::HQ_REFRESHER_TRAINING;
            }
            $sql->whereIn('slot.position_id', $ids);
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

    public static function didPersonPassForYear(int $personId, int $positionId, int $year): bool
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

    public static function didPersonPassSession(int $personId, int $slotId): bool
    {
        return self::where('person_id', $personId)
            ->where('passed', true)
            ->where('slot_id', $slotId)
            ->exists();
    }

    /**
     * Delete all records referring to a slot. Used by slot deletion.
     * @param int $slotId
     */

    public static function deleteForSlot(int $slotId): void
    {
        self::where('slot_id', $slotId)->delete();
    }

    public function setRankAttribute($value)
    {
        $this->attributes['rank'] = empty($value) ? null : $value;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraineeNote extends ApiModel
{
    protected $table = 'trainee_note';
    public $timestamps = true;
    protected bool $auditModel = true;

    // Trainee Notes are not directly accessed
    protected $guarded = [];

    public function person_source(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    /**
     * Find all the training notes for a person in a slot
     *
     * @param int $personId person to find
     * @param int $slotId slot to find
     * @return Collection
     */

    public static function findAllForPersonSlot(int $personId, int $slotId): Collection
    {
        return self::where('person_id', $personId)
            ->where('slot_id', $slotId)
            ->orderBy('created_at')
            ->with('person_source:id,callsign')
            ->get();
    }

    /**
     * Delete all records referring to a slot. Used by slot deletion.
     *
     * @param int $slotId Parent slot ID
     * @return void
     */

    public static function deleteForSlot($slotId): void
    {
        self::where('slot_id', $slotId)->delete();
    }


    /**
     * Record the training notes for a person
     *
     * @param int $personId Person to record
     * @param int $slotId training slot
     * @param string $note the note itself
     * @param bool $isLog true if the note is an audit note about the rank
     * @return void
     */

    public static function record(int $personId, int $slotId, string $note, bool $isLog = false): void
    {
        self::create([
            'person_id' => $personId,
            'slot_id' => $slotId,
            'note' => $note,
            'is_log' => $isLog,
            'person_source_id' => Auth::id(),
        ]);
    }

}

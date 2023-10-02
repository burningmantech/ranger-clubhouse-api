<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class PersonSlot extends ApiModel
{
    protected $table = 'person_slot';

    protected $fillable = [
        'person_id',
        'slot_id'
    ];

    protected $casts = [
        'created_at' => 'datetime'
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
     * Is a person signed up for a given slot?
     *
     * @param int $personId
     * @param int $slotId
     * @return bool
     */

    public static function haveSlot(int $personId, int $slotId): bool
    {
        return self::where('person_id', $personId)->where('slot_id', $slotId)->exists();
    }

    /**
     * Delete all slot sign-ups. Used by slot deletion.
     *
     * @param int $slotId
     */

    public static function deleteForSlot(int $slotId): void
    {
        $rows = self::where('slot_id', $slotId)->get();
        $user = Auth::user();

        foreach ($rows as $row) {
            ActionLog::record($user, 'person-slot-remove', 'slot deletion', ['slot_id' => $slotId], $row->person_id);
        }

        self::where('slot_id', $slotId)->delete();
    }
}

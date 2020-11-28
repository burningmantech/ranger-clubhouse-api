<?php

namespace App\Models;

use Carbon\Carbon;

use App\Models\ApiModel;
use App\Models\ActionLog;
use App\Models\Person;
use App\Models\Slot;

use Illuminate\Support\Facades\Auth;

class PersonSlot extends ApiModel
{
    protected $table = 'person_slot';

    protected $fillable = [
        'person_id',
        'slot_id'
    ];

    protected $casts = [
        'timestamp' => 'datetime'
    ];

    public function person() {
        return $this->belongsTo(Person::class);
    }

    public function slot() {
        return $this->belongsTo(Slot::class);
    }

    /*
     * Is a person signed up for a given slot?
     */

    public static function haveSlot($personId, $slotId) {
        return self::where('person_id', $personId)->where('slot_id', $slotId)->exists();
    }

    /*
     * Delete all records referring to a slot. Used by slot deletion.
     */

    public static function deleteForSlot($slotId) {
        $rows = self::where('slot_id', $slotId)->get();
        $user = Auth::user();

        foreach ($rows as $row) {
            ActionLog::record($user, 'person-slot-remove', 'slot deletion', ['slot_id' => $slotId], $row->person_id);
        }

        self::where('slot_id', $slotId)->delete();
    }
}

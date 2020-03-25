<?php

namespace App\Models;

use Carbon\Carbon;

use App\Models\ApiModel;
use App\Models\Person;
use App\Models\Slot;

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
     * Delete all records refering to a slot. Used by slot deletion.
     */

    public static function deleteForSlot($slotId) {
        return self::where('slot_id', $slotId)->delete();
    }
}

<?php

namespace App\Models;

use Carbon\Carbon;

use App\Models\ApiModel;
use App\Models\PositionCredit;
use App\Models\ApihouseResult;
use App\Helpers\DateHelper;

use Illuminate\Support\Facades\DB;

class PersonSlot extends ApiModel
{
    protected $table = 'person_slot';

    protected $fillable = [
        'person_id',
        'slot_id'
    ];

    protected $casts = [
        'timestamp' => 'timestamp'
    ];

    public static function haveSlot($personId, $slotId) {
        return self::where('person_id', $personId)->where('slot_id', $slotId)->exists();
    }

    public static function deleteForSlot($slotId) {
        return self::where('slot_id', $slotId)->delete();
    }
}

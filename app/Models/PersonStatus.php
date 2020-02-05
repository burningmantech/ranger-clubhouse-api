<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\ApiModel;

use App\Models\Person;

class PersonStatus extends ApiModel
{
    protected $table = 'person_status';

    protected $guarded = [ ];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function person_source()
    {
        return $this->belongsTo(Person::class);
    }

    public static function record($personId, $oldStatus, $newStatus, $reason, $personSourceId)
    {
        self::create([
            'person_id'        => $personId,
            'person_source_id' => $personSourceId,
            'old_status'       => $oldStatus,
            'new_status'       => $newStatus,
            'reason'           => $reason
        ]);
    }
}

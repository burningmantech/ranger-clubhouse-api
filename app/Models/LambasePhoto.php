<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Models\Person;

/*
 * Purely for conversion and archival purposes
 */

class LambasePhoto extends ApiModel
{
    protected $table = 'lambase_photo';

    protected $dates = [
        'lambase_date'
    ];

    public function person() {
        return $this->belongsTo(Person::class);
    }
}

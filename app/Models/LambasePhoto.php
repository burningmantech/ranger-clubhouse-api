<?php

namespace App\Models;

/*
 * Purely for conversion and archival purposes
 */

class LambasePhoto extends ApiModel
{
    protected $table = 'lambase_photo';

    protected $casts = [
        'lambase_date' => 'datetime'
    ];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }
}

<?php

namespace App\Models;

use App\Models\ApiModel;

class HelpHit extends ApiModel
{
    protected $table = 'help_hit';

    protected $fillable = [
        'help_id',
        'person_id'
    ];

    public static function record($helpId, $personId) {
        self::create([ 'help_id' => $helpId, 'person_id' => $personId ]);
    }
}

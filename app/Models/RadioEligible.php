<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Traits\HasCompositePrimaryKey;

class RadioEligible extends ApiModel
{
    use HasCompositePrimaryKey;

    protected $table = 'radio_eligible';

    protected $primaryKey = [ 'person_id', 'year' ];

    protected $fillable = [
        'person_id',
        'year',
        'max_radios'
    ];

    public static function findForPersonYear($personId, $year) {
        return self::where('person_id', $personId)->where('year', $year)->first();
    }

    public static function firstOrNewForPersonYear($personId, $year) {
        return self::firstOrNew([ 'person_id' => $personId, 'year' => $year ]);
    }
}

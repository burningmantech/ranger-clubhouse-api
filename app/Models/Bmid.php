<?php

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;

use App\Models\ApiModel;
use App\Models\Person;

use App\Helpers\SqlHelper;

class Bmid extends ApiModel
{
    use HasCompositePrimaryKey;

    protected $table = 'bmid';

    protected $primaryKey = [ 'person_id', 'year' ];

    const MEALS_TYPES = [
        'all',
        'event',
        'event+post',
        'post',
        'pre',
        'pre+event',
        'pre+post'
    ];

    // Allow mass assignment - BMIDs are an exclusive Admin function.
    protected $guarded = [ ];

    protected $casts = [
        'showers'               => 'bool',
        'org_vehicle_insurance' => 'bool',
        'create_datetime'       => 'datetime',
        'modified_datetime'     => 'timestamp'
    ];

    public static function boot()
    {
        parent::boot();

        self::creating(function($model) {
            // TODO - adjust bmid scheme to default to current timestamp
            if ($model->create_datetime == null) {
                $model->create_datetime = SqlHelper::now();
            }

            if (empty($model->status)) {
                $model->status = 'in_prep';
            }
        });
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public static function findForPersonYear($personId, $year) {
        return self::where('person_id', $personId)->where('year', $year)->first();
    }

    public static function firstOrNewForPersonYear($personId, $year)
    {
        return self::firstOrNew([ 'person_id' => $personId, 'year' => $year ]);
    }
}

<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Models\Person;

class Motd extends ApiModel
{
    protected $table = 'motd';
    public $timestamps = true;

    protected $guarded = [
        'person_id',
        'created_at',
        'updated_at'
    ];

    protected $rules = [
        'message' => 'required|string',
        'is_alert' => 'sometimes|boolean'
    ];

    public function person() {
        return $this->belongsTo(Person::class);
    }

    public static function findAll()
    {
        return self::orderBy('created_at')->with('person:id,callsign')->get();
    }
}

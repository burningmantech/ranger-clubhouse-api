<?php

namespace App\Models;

use App\Models\ApiModel;

use App\Models\Person;
use App\Models\AssetAttachment;

class AssetPerson extends ApiModel
{
    protected $table = 'asset_person';

    protected $fillable = [
        'person_id',
        'asset_id',
        'checked_out',
        'checked_in',
        'attachment_id'
    ];

    protected $casts = [
        'checked_out' => 'datetime',
        'checked_in'  => 'datetime',
    ];

    protected $rules = [
        'person_id' => 'required|integer',
        'asset_id'  => 'required|integer',
    ];

    public function person() {
        return $this->belongsTo('App\Models\Person');
    }

    public function attachment() {
        return $this->belongsTo('App\Models\AssetAttachment');
    }
}

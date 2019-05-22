<?php

namespace App\Models;

use App\Models\ApiModel;

class PersonPhoto extends ApiModel
{
    protected $table = 'person_photo';
    protected $primaryKey = 'person_id';
    public $timestamps = true;
    protected $guarded = [];

    protected $dates = [
        'expired_at',
        'lambase_date'
    ];

    public function isApproved() {
        return $this->status == 'approved';
    }
}

<?php

namespace App\Models;

use App\Models\ApiModel;

class Alert extends ApiModel
{
    protected $table = 'alert';
    protected $auditModel = true;

    const SHIFT_CHANGE               = 1;
    const SHIFT_MUSTER               = 2;
    const EMEREGENCY_BROADCAST       = 3;
    const RANGER_SOCIALS             = 4;
    const TICKETING                  = 5;
    const ON_SHIFT                   = 6;
    const TRAINING                   = 7;
    const CLUBHOUSE_NOTIFY_ON_PLAYA  = 8;
    const CLUBHOUSE_NOTIFY_PRE_EVENT = 9;
    const SHIFT_CHANGE_PRE_EVENT     = 10;
    const SHIFT_MUSTER_PRE_EVENT     = 11;

    // The following are not part of the RBS and are email only
    const RANGER_CONTACT             = 12;
    const MENTOR_CONTACT             = 13;

    protected $fillable = [
        'title',
        'description',
        'on_playa',
    ];

    protected $casts = [
        'on_playa'      => 'boolean',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime'
    ];

    protected $rules = [
        'title' => 'required|string',
        'description' => 'required|string',
        'on_playa'  => 'boolean'
    ];

    protected $appends = [
        'sms_only',
        'email_only',
        'no_opt_out',
    ];

    public static function findAll()
    {
        return self::orderBy('on_playa', 'desc', 'title', 'asc')->get();
    }

    public function getSmsOnlyAttribute() {
        return ($this->id == Alert::ON_SHIFT);
    }

    public function getEmailOnlyAttribute() {
        return ($this->id == Alert::RANGER_CONTACT || $this->id == Alert::MENTOR_CONTACT);
    }

    public function getNoOptOutAttribute() {
        return ($this->id == Alert::EMEREGENCY_BROADCAST);
    }
}

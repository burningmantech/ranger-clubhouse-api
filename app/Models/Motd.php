<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Models\Person;

class Motd extends ApiModel
{
    protected $table = 'motd';
    protected $auditModel = true;
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

    public static function findForStatus($status) {
        switch ($status) {
            case Person::AUDITOR:
                $type = 'for_auditors';
                break;
            case Person::PROSPECTIVE:
            case Person::ALPHA:
                $type = 'for_pnvs';
                break;
            default:
                if (in_array($status, Person::NO_MESSAGES_STATUSES)) {
                    return [];
                }
                $type = 'for_rangers';
                break;
        }

        return self::where($type, 1)->orderBy('created_at')->get();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionLog extends Model
{
    protected $table = 'action_logs';

    protected $guarded = [ ];

    // created_at is handled by the database itself
    public $timestamps = false;

    public static function record($person, $event, $message, $data=null, $targetPersonId=null) {
        $log = new ActionLog;
        $log->event = $event;
        $log->person_id = $person ? $person->id : null;
        $log->message = $message;
        $log->target_person_id = $targetPersonId;

        if ($data) {
            $log->data = json_encode($data);
        }

        $log->save();
    }
}

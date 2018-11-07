<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ApiModel;

class ContactLog extends ApiModel
{
    protected $table = 'contact_log';

    protected $fillable = [
        'sender_person_id',
        'recipient_person_id',
        'action',
        'recipient_address',
        'subject',
        'message'
    ];

    public static function record($senderId, $recipientId, $action, $email, $subject, $message)
    {
        $log = new ContactLog([
            'sender_person_id'    => $senderId,
            'recipient_person_id' => $recipientId,
            'action'              => $action,
            'recipient_address'   => $email,
            'subject'             => $subject,
            'message'             => $message
        ]);

        $log->save();
    }
}

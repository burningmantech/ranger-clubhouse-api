<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function recipient_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function sender_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public static function findForSenderYear($personId, $year)
    {
        return self::with(['recipient_person:id,callsign'])
            ->where('sender_person_id', $personId)
            ->whereYear('sent_at', $year)
            ->orderBy('sent_at')->get();
    }

    public static function findForRecipientYear($personId, $year)
    {
        return self::with(['sender_person:id,callsign'])
            ->where('recipient_person_id', $personId)
            ->whereYear('sent_at', $year)
            ->orderBy('sent_at')->get();
    }


    public static function record($senderId, $recipientId, $action, $email, $subject, $message)
    {
        $log = new ContactLog([
            'sender_person_id' => $senderId,
            'recipient_person_id' => $recipientId,
            'action' => $action,
            'recipient_address' => $email,
            'subject' => $subject,
            'message' => $message
        ]);

        $log->save();
    }
}

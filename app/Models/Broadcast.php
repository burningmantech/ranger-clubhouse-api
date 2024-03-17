<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Broadcast extends ApiModel
{
    protected $table = "broadcast";

    // allow mass assignment
    protected $guarded = [];

    /*
     * Broadcast Types
     */

    const string TYPE_GENERAL = 'general';    // General non-emergency announcement, like the allcom mailing list.
    const string TYPE_SLOT_EDIT = 'slot-edit';  // Slot time change or deletion cancellation (comes from Edit Slots page)
    const string TYPE_POSITION = 'position';   // People who hold a position
    const string TYPE_SLOT = 'slot';       // People who are signed up for a shift

    const string TYPE_ONSHIFT = 'onshift';    // Send to people on shift (radio repeater down, city split happening)
    const string TYPE_RECRUIT_DIRT = 'recruit-dirt'; // Request for more people for a shift, dirt
    const string TYPE_RECRUIT_POSITION = 'recruit-position'; // Request for more people based on position
    const string TYPE_EMERGENCY = 'emergency'; // All hands on deck - limited to playa.


    // Message was handed off to the SMTP server,
    // or sms vendor. 'sent' does not indicate delivery.
    const string STATUS_SENT = 'sent';
    // SMTP server or SMS service could not be contacted.
    const string STATUS_SERVICE_FAIL = 'service-fail';
    // Verify code sent to target phone
    const string STATUS_VERIFY = 'verify';
    // Stop request  received
    const string STATUS_STOP = 'stop';
    // Start request received
    const string STATUS_START = 'start';
    // Help request was received from phone
    const string STATUS_HELP = 'help';
    // Administration command - reply with a 24 hour activity summary
    const string STATUS_STATS = 'stats';
    // Next shift request
    const string STATUS_NEXT = 'next';
    // Unknown command / message was received from phone
    const string STATUS_UNKNOWN_COMMAND = 'unknown-command';
    // Message received from an unknown phone number
    const string STATUS_UNKNOWN_PHONE = 'unknown-phone';

    // Reply sent in response to an inbound message
    const string STATUS_REPLY = 'reply';

    // Email or SMS message was bounced (not used currently, requires status callback)
    const string STATUS_BOUNCED = 'bounced';
    // SMS message was blocked (not used currently, requires status callback)
    const string STATUS_BLOCKED = 'blocked';

    public $appends = [
        'people'
    ];

    public $people;

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function retry_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(BroadcastMessage::class);
    }

    public function failed_messages(): HasMany
    {
        return $this->hasMany(BroadcastMessage::class)->where('status', Broadcast::STATUS_SERVICE_FAIL);
    }

    public static function findWithFailedMessages($broadcastId)
    {
        return self::where('id', $broadcastId)
            ->with('failed_messages', 'failed_messages.person:id,callsign,status')
            ->firstOrFail();
    }

    public static function findLogs($query)
    {
        $year = $query['year'] ?? 0;
        $failedOnly = $query['failed'] ?? false;
        $lastDay = $query['lastday'] ?? false;

        $sql = self::with([
            'alert:id,title',
            'sender:id,callsign',
            'retry_person:id,callsign'
        ])->orderBy('created_at', 'desc');

        if ($year) {
            $sql->whereYear('created_at', $year);
        } else if ($lastDay) {
            $sql->whereRaw('created_at >= DATE_SUB(?, INTERVAL 1 DAY)', [now()]);
        }

        if ($failedOnly) {
            $sql->where(function ($q) {
                $q->where('sms_failed', '>', 0);
                $q->orWhere('email_failed', '>', 0);
            });
        }

        $logs = $sql->get();

        foreach ($logs as $log) {
            $messages = BroadcastMessage::where('broadcast_id', $log->id)
                ->with(['person:id,callsign,first_name,last_name'])
                ->get()
                ->sortBy('person.callsign', SORT_NATURAL | SORT_FLAG_CASE)->values();

            $people = [];
            foreach ($messages as $message) {
                $personId = $message->person_id;
                if (!isset($people[$personId])) {
                    $people[$personId] = [
                        'first_name' => $message->person->first_name,
                        'last_name' => $message->person->last_name,
                        'callsign' => $message->person->callsign,
                    ];
                }

                $people[$personId][$message->address_type] = [
                    'status' => $message->status,
                    'address' => $message->address
                ];
            }
            $log->people = array_values($people);
        }

        return $logs;
    }

    public function getPeopleAttribute()
    {
        return $this->people;
    }
}

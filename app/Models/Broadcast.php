<?php

namespace App\Models;

use App\Models\ApiModel;

class Broadcast extends ApiModel {
    protected $table = "broadcast";

    // allow mass assignment
    protected $guarded = [];

    // Message was handed off to the SMTP server,
    // or sms vendor. 'sent' does not indicate delivery.
    const STATUS_SENT = 'sent';
    // SMTP server or SMS service could not be contacted.
    const STATUS_SERVICE_FAIL = 'service-fail';
    // Verify code sent to target phone
    const STATUS_VERIFY = 'verify';
    // Stop request  received
    const STATUS_STOP = 'stop';
    // Start request received
    const STATUS_START = 'start';
    // Help request was received from phone
    const STATUS_HELP = 'help';
    // Administration command - reply with a 24 hour activity summary
    const STATUS_STATS = 'stats';
    // Next shift request
    const STATUS_NEXT = 'next';
    // Unknown command / message was received from phone
    const STATUS_UNKNOWN_COMMAND = 'unknown-command';
    // Message received from an unknown phone number
    const STATUS_UNKNOWN_PHONE = 'unknown-phone';

    // Reply sent in response to an inbound message
    const STATUS_REPLY = 'reply';

    // Email or SMS message was bounced (not used currently, requires status callback)
    const STATUS_BOUNCED = 'bounced';
    // SMS message was blocked (not used currently, requires status callback)
    const STATUS_BLOCKED = 'blocked';

};

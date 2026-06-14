<?php

namespace App\Lib;

/**
 * The seam for outbound SMS. Resolve with app(SmsGateway::class).
 *
 * Adapters: TwilioSmsGateway (production), FakeSmsGateway (tests).
 */

interface SmsGateway
{
    /**
     * Broadcast a message to a set of phone numbers.
     *
     * @param string[] $phoneNumbers numbers to send to
     * @param string $message text to send
     * @return string status ('sent')
     * @throws SMSException if the provider reported an error
     */

    public function broadcast(array $phoneNumbers, string $message): string;

    /**
     * Determine whether a phone number can receive SMS.
     *
     * @param string $phoneNumber number in E.164 format
     * @return bool true if the number can text
     */

    public function isSMSCapable(string $phoneNumber): bool;
}

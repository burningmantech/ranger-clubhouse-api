<?php

namespace Tests\Support;

use App\Lib\SmsGateway;
use App\Lib\SMSException;

/**
 * In-memory SmsGateway adapter for tests. Records what would have been sent and
 * can simulate provider failures, so callers' success and failure paths are both
 * exercisable without touching the network.
 */

class FakeSmsGateway implements SmsGateway
{
    /** @var array<int, array{numbers: string[], message: string}> */
    public array $sent = [];

    public bool $capable = true;

    public bool $throwOnBroadcast = false;

    public function broadcast(array $phoneNumbers, string $message): string
    {
        if ($this->throwOnBroadcast) {
            throw new SMSException('simulated SMS failure');
        }

        $this->sent[] = ['numbers' => $phoneNumbers, 'message' => $message];

        return 'sent';
    }

    public function isSMSCapable(string $phoneNumber): bool
    {
        return $this->capable;
    }
}

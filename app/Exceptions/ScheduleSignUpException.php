<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when the requested sign-up action fails due to the slot being full or  slot is missing (almost impossible,
 * however, it could happen where the slot is deleted right as the sign-up is attempted).
 */
class ScheduleSignUpException extends Exception
{
    public function __construct(string        $message,
                                public ?int   $signUps = null,
                                public ?array $linkedSlots = null,
                                public ?int   $combinedMax)
    {
        parent::__construct($message);
    }
}
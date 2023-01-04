<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when Moodle is down for maintenance
 */

class MoodleDownForMaintenanceException extends Exception
{
    protected $message = 'Moodle is down for maintenance';
}
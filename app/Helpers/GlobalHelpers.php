<?php
/*
 * Global Helpers used everywhere throughout the application.
 */

use App\Helpers\SqlHelper;

use App\Models\ErrorLog;
use App\Models\Setting;
use Carbon\Carbon;

use Illuminate\Support\Facades\Mail;


if (!function_exists('setting')) {
    /**
     * Retrieve a configuration variable possibly stored in the database.
     * Alias for Setting::get().
     *
     * @param mixed $name - setting name
     * @param bool $throwOnEmpty - throw an exception if the value is empty. (false is ignored.)
     * @return mixed setting value
     */

    function setting($name, $throwOnEmpty=false)
    {
        return Setting::get($name, $throwOnEmpty);
    }
}

/**
 * Send an email. Alias for Mail:to()->send() with exception handling.
 *
 * @param mixed $email string, string with a comma(s), or string array of email addresses to send
 * @param Mailable $message the message to send
 * @param bool $queueMail true if the email is to be queued for delivery
 * @return boolean true if mail was successfully queued, false if an exception happened.
 */

if (!function_exists('mail_to')) {
    function mail_to($email, $message, $queueMail=false)
    {
        if (is_string($email) && strpos($email, ',') !== false) {
            $email = explode(',', $email);
        }

        try {
            $to = Mail::to($email);
            if ($queueMail && !env('APP_DEBUG')) {
                $to->queue($message);
            } else {
               $to->send($message);
            }
            return true;
        } catch (\Swift_TransportException $e) {
            ErrorLog::recordException($e, 'email-exception', [
                    'type'    => 'mail-to',
                    'email'   => $email,
                    'message' => $message
             ]);

             return false;
        }
    }
}

/**
 * Retrieve the current year.
 *
 * Support for groundhog day server. When the GroundhogDayServer configuration
 * variable is true, use the database year. otherwise use the system year.
 *
 * @return integer the current year (either real or simulated)
 */

if (!function_exists('current_year')) {
    function current_year() : int
    {
        static $year;

        if (config('clubhouse.GroundhogDayServer')) {
            if ($year) {
                return $year;
            }

            $year = SqlHelper::now()->year;
            return $year;
        }
        return date('Y');
    }
}

if (!function_exists('event_year')) {
    function event_year() : int
    {
        $now = now();
        if ($now->month >= 9) {
            // September or later.
            $eventEnd = (new Carbon("first Monday of September"))->addDays(7);

            if ($now->gte($eventEnd)) {
                // A week past Labor Day until the end of the year is really next year.
                return $now->year + 1;
            }
        }

        return $now->year;
    }
}

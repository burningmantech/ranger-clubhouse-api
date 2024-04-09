<?php
/*
 * Global Helpers used everywhere throughout the application.
 */

use App\Models\ErrorLog;
use App\Models\Person;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

if (!function_exists('setting')) {
    /**
     * Retrieve a configuration variable possibly stored in the database.
     * Alias for Setting::get().
     *
     * @param mixed $name - setting name
     * @param bool $throwOnEmpty - throw an exception if the value is empty. (false is ignored.)
     * @return mixed setting value
     */

    function setting($name, $throwOnEmpty = false)
    {
        return Setting::getValue($name, $throwOnEmpty);
    }
}

/**
 * Send an email. Alias for Mail:to()->send() with exception handling.
 *
 * @param string|array $email string, string with a comma(s), or string array of email addresses to send
 * @param Mailable $message the message to send
 * @param bool $queueMail true if the email is to be queued for delivery
 * @return bool true if mail was successfully queued, false if an exception happened.
 */

if (!function_exists('mail_to')) {
    function mail_to(string|array $email, Mailable $message, bool $queueMail = false, $personId = null): bool
    {
        prevent_if_ghd_server('Sending email');

        if (is_string($email) && str_contains($email, ',')) {
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
        } catch (TransportExceptionInterface $e) {
            ErrorLog::recordException($e, 'email-exception', [
                'type' => 'mail-to',
                'email' => $email,
                'message' => $message
            ]);

            return false;
        }
    }
}

if (!function_exists('mail_to_person')) {
    function mail_to_person(Person $person, Mailable $message, bool $queueMail = false): bool
    {
        return mail_to($person->email, $message, $queueMail, $person->id);
    }
}

/**
 * Retrieve the current year.
 *
 * Support for groundhog day server. When the GroundhogDayTime configuration
 * variable is true, use the database year. otherwise use the system year.
 *
 * @return integer the current year (either real or simulated)
 */

if (!function_exists('current_year')) {
    function current_year(): int
    {
        static $year;

        if (config('clubhouse.GroundhogDayTime')) {
            if ($year) {
                return $year;
            }

            $year = now()->year;
            return $year;
        }
        return date('Y');
    }
}

if (!function_exists('event_year')) {
    function event_year(): int
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

if (!function_exists('maintenance_year')) {
    /**
     * The maintenance year is from the end of the event til end of July of the following year.
     *
     * @return int
     */
    function maintenance_year(): int
    {
        $now = now();
        return $now->month < 8 ? $now->year - 1 : $now->year;
    }
}

if (!function_exists('request_ip')) {
    function request_ip(): string
    {
        $header = request()->header('X-Forwarded-For');
        return !empty($header) ? $header : implode(',', request()->ips());
    }
}

if (!function_exists('is_ghd_server')) {
    /**
     * Is this a Ground Hog Day server?
     * @return bool
     */

    function is_ghd_server(): bool
    {
        return !empty(config('clubhouse.GroundhogDayTime'));
    }
}

if (!function_exists('prevent_if_ghd_server')) {
    /**
     * Thrown an exception if running on the GHD server (training server).
     * @param $action
     * @throws AuthorizationException
     */

    function prevent_if_ghd_server($action)
    {
        if (!is_ghd_server()) {
            return;
        }

        throw new AuthorizationException("$action is prevented on the training server.");
    }
}


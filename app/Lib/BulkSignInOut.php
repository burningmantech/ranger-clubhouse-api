<?php

namespace App\Lib;

use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\Position;
use App\Models\Schedule;
use App\Models\Timesheet;
use App\Models\TimesheetLog;

/*
 * Parse and verify the Bulk Sign In / Out lines sent down
 */

class BulkSignInOut
{
    /*
     * Time format 07:30 or 0730
     */

    const TIME_REGEXP = '/^(\d{1,2}):?(\d{2})$/';

    /*
     * Date formats - MM-DD, MM/DD, MM-DD-YYYY, MM/DD/YYYY
     */

    const DATE_REGEXP = '/^(\d{1,2})[-\/](\d{1,2})([-\/]\d+)?$/';

    const DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * Parse out the bulk sign in/out lines
     *
     * @param $lines
     * @return array
     */

    public static function parse($lines)
    {
        $lines = explode("\n", $lines);
        $positions = Position::all();
        $positionTitles = [];
        foreach ($positions as $p) {
            $positionTitles[strtolower($p['title'])] = $p;
        }

        $entries = [];
        $haveError = false;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line == '') {
                continue;
            }
            $columns = explode(',', $line);
            // Clean up the columns
            foreach ($columns as $idx => $col) {
                $columns[$idx] = trim($col);
            }

            $entry = null;
            if (!self::processLine($columns, $positionTitles, $entry)) {
                $haveError = true;
            }

            $entries[] = $entry;
        }

        return [$entries, $haveError];
    }

    /*
     * Process a sign in/out line. Lookup the callsign, find the position,
     * check to see if the person is signed in already.
     *
     * The following formats are supported:
     *
     * callsign,position         - sign a person in to position starting now
     * callsign,time             - sign out a person at time
     * callsign,date,time        - sign out a person at date & time
     * callsign,position,time    - sign in a person to position at time
     * callsign,position,time,time - sign in & out  a person in and out to position from today at time to time.
     * callsign,position,date,time - sign in a person in to position at date & time.
     * callsign,position,date,time,time - sign in & out a person to position at date & time to time
     * callsign,position,date,time,date,time - sign in & out a person to position at date & time to date & time.
     *
     * @param array $columns line to process, each element represents a column
     * @param array $positionTitles position array, key is title lower-cased
     * @return object processed line
     */

    public static function processLine($columns, $positionTitles, &$entry)
    {
        $signin = null;
        $signout = null;
        $position = null;

        $errors = [];

        if (empty($columns[0])) {
            $action = 'unknown';
            $errors[] = 'Callsign not specified';
            $entry = (object)[
                'action' => 'unknown',
                'errors' => ['Callsign not specified']
            ];
            return false;
        }

        $count = count($columns);

        $callsign = $columns[0];
        $field = $columns[1] ?? '';
        $year = current_year();

        switch ($count) {
            case 1:
                $action = 'unknown';
                $errors[] = 'too few columns - need at least a time or position';
                break;

            /*
             * callsign,time       - sign out @ time
             * callsign,position   - sign in now w/position
             */
            case 2:
                if (self::isTime($field)) {
                    $action = 'out';
                    $signout = self::parseTime($field, 'sign out', $errors);
                } else {
                    $action = 'in';
                    $position = $field;
                }
                break;

            /*
             * callsign,date,time  - sign out @ date & time
             * callsign,position,time  - sign in @ time w/position
             */
            case 3:
                if (self::isDate($field)) {
                    $action = 'out';
                    $signout = self::parseDateAndTime($field, $columns[2], 'signout', $errors);
                } else {
                    $action = 'in';
                    $signin = self::parseTime($columns[2], 'sign in', $errors);
                    $position = $field;
                }
                break;

            /*
             * callsign,position,time,time - sign in/sign out
             * callsign,position,date,time - sign in
             */
            case 4:
                $position = $field;
                if (self::isTime($columns[2])) {
                    $action = 'inout';
                    $signin = self::parseTime($columns[2], 'sign in', $errors);
                    $signout = self::parseTime($columns[3], 'sign out', $errors);
                    if ($signin > $signout) {
                        // graveyard or swing shifts e.g., 23:45 -> 06:45
                        $signout = strtotime("+1 day", $signout);
                    }
                } else {
                    $action = 'in';
                    $signin = self::parseDateAndTime($columns[2], $columns[3], 'sign in', $errors);
                }
                break;
            /*
             * callsign,position,date,time,time - sign in & out
             */

            case 5:
                $position = $field;
                $action = 'inout';
                $signin = self::parseDateAndTime($columns[2], $columns[3], 'sign in', $errors);
                $signout = self::parseTime($columns[4], 'sign out', $errors);
                if ($signin && $signout && $signin > $signout) {
                    // graveyard or swing shifts e.g., 23:45 -> 06:45
                    $signout = strtotime(date('Y/m/d ', $signin) . ' ' . date('H:i', $signout));
                    $signout = strtotime("+1 day", $signout);
                } else {
                    $signout = strtotime(date('Y/m/d ', $signin) . ' ' . date('H:i', $signout));
                }
                break;

            /*
             * callsign,position,date,time,date,time - sign in/sign out
             */
            case 6:
                $action = 'inout';
                $position = $field;
                $signin = self::parseDateAndTime($columns[2], $columns[3], 'sign in', $errors);
                $signout = self::parseDateAndTime($columns[4], $columns[5], 'sign out', $errors);
                if ($signin && $signout) {
                    if ($signin >= $signout) {
                        $errors[] = 'sign in time starts on or after sign out';
                    }
                }
                break;

            default:
                $errors[] = 'too many columns - format not understood.';
                $action = 'unknown';
                $signin = '';
                $signout = '';
                $position = '';
                break;
        }

        $positionId = null;
        $timesheetId = null;

        $person = Person::findByCallsign($callsign);
        if ($person) {
            $personId = $person->id;
            $callsign = $person->callsign;
        }  else {
            $personId = null;
        }

        if (!$personId) {
            $errors[] = "callsign '$callsign' not found";
        } elseif (empty($errors)) {
            $timesheet = Timesheet::findPersonOnDuty($personId);
            $timesheetId = $timesheet ? $timesheet->id : null;

            if ($action == 'in' && $timesheetId) {
                $errors[] = 'is on duty';
            } elseif ($action == 'out') {
                if (!$timesheet) {
                    $errors[] = 'is not on duty';
                } else if ($timesheet->on_duty->timestamp > $signout) {
                    $errors[] = 'sign out is before sign in time';
                }
            }

            if ($action == 'inout') {
                $checkIn = date(self::DATETIME_FORMAT, $signin);
                $checkOut = date(self::DATETIME_FORMAT, $signout);
                $overlap = Timesheet::findOverlapForPerson($personId, $checkIn, $checkOut);

                if ($overlap) {
                    $errors[] = "overlapping or duplicate timesheet. Position {$overlap->position->title} Start {$overlap->on_duty} End {$overlap->off_duty} ";
                }
            } elseif ($timesheet) {
                $signin = $timesheet->on_duty->timestamp;
            }
        }

        if ($position != '') {
            $title = strtolower($position);
            if (isset($positionTitles[$title])) {
                $p = $positionTitles[$title];
                $positionId = $p->id;
                $position = $p->title;

                if ($personId) {
                    $havePosition = PersonPosition::havePosition($personId, $positionId);
                    if (!$havePosition) {
                        $errors[] = "does not hold the position '$position'";
                    }
                }
            } else {
                $errors[] = "position '$position' not found";
            }
        } elseif ($timesheetId) {
            $positionId = $timesheet->position_id;
            $position = $timesheet->position->title;
        }

        $entry = (object)[
            'person_id' => $personId,
            'callsign' => $callsign,
            'action' => $action,
            'timesheet_id' => $timesheetId,
            'position' => $position,
            'position_id' => $positionId,
            'signin' => $signin ? date(self::DATETIME_FORMAT, $signin) : null,
            'signout' => $signout ? date(self::DATETIME_FORMAT, $signout) : null,
            'errors' => $errors
        ];

        return empty($errors);
    }

    public static function isTime($value)
    {
        if ($value == '007') {
            return false;
        }
        return preg_match(self::TIME_REGEXP, $value);
    }


    public static function isDate($value)
    {
        if ($value == '007') {
            return false;
        }
        return preg_match(self::DATE_REGEXP, $value);
    }

    public static function parseTime($value, $name, &$errors)
    {
        if (!preg_match(self::TIME_REGEXP, $value, $match)) {
            $errors[] = "$name is not a valid time format [$value]";
            return null;
        }

        $time = strtotime("$match[1]:$match[2]");
        if ($time === false) {
            $errors[] = "$name is not a valid time [$value]";
            return null;
        }

        return $time;
    }

    public static function parseDateAndTime($date, $time, $name, &$errors)
    {
        if (!preg_match(self::DATE_REGEXP, $date, $match)) {
            $errors[] = "$name has an invalid date [$date]";
            return null;
        }

        // Was a year given?
        if (isset($match[4])) {
            $year = $match[4];
            // Only a two digit year? make it a 4 digit year.
            if (strlen($year) == 2) {
                $year = '20' . $year;
            }
        } else {
            $year = current_year();
        }

        $date = "$year/$match[1]/$match[2]";

        if (!preg_match(self::TIME_REGEXP, $time, $match)) {
            $errors[] = "$name  has an invalid time [$time]";
            return null;
        }

        $time = "$match[1]:$match[2]";
        $dateTime = strtotime("$date $time");
        if ($dateTime === false) {
            $errors[] = "$name has an invalid date and/or time [$date $time]";
            return null;
        }

        return $dateTime;
    }


    public static function process($entries, int $userId)
    {
        $haveError = false;
        foreach ($entries as $entry) {
            $personId = $entry->person_id;
            $positionId = $entry->position_id;
            $signin = $entry->signin;
            $signout = $entry->signout;
            $action = $entry->action;

            $data = [];

            switch ($action) {
                case 'inout':
                    // Sign in & out - create timesheet
                    $timesheet = new Timesheet([
                        'person_id' => $personId,
                        'position_id' => $positionId,
                        'on_duty' => $signin,
                        'off_duty' => $signout,
                    ]);
                    $event = TimesheetLog::CREATED;
                    $data = [
                        'position_id' => $positionId,
                        'on_duty' => (string)$signin,
                        'off_duty' => (string)$signout,
                    ];
                    break;

                case 'in':
                    // Sign in - create timesheet
                    if (empty($signin)) {
                        $signin = now();
                    }

                    $timesheet = new Timesheet([
                        'person_id' => $personId,
                        'position_id' => $positionId,
                        'on_duty' => $signin,
                    ]);
                    $entry->signin = $signin;

                    $event = TimesheetLog::SIGNON;
                    $data = [
                        'position_id' => $positionId,
                        'on_duty' => (string)$signin
                    ];
                    break;

                case 'out':
                    $timesheetId = $entry->timesheet_id;
                    $timesheet = Timesheet::find($timesheetId);
                    if (!$timesheet) {
                        // Impossible condition?
                        $entry->errors = ["cannot find timesheet id=[$timesheetId]? "];
                        $haveError = true;
                        continue 2;
                    }

                    if (empty($signout)) {
                        $signout = now();
                    }
                    $timesheet->off_duty = $signout;
                    $event = TimesheetLog::SIGNOFF;
                    $data = [
                        'off_duty' => (string)$signout
                    ];
                    break;
            }

            if (!$timesheet->slot_id) {
                // Try to associate a sign up with the entry
                $timesheet->slot_id = Schedule::findSlotIdSignUpByPositionTime($timesheet->person_id, $timesheet->position_id, $timesheet->on_duty);
            }

            $timesheet->auditReason = 'bulk sign in/out';
            if ($timesheet->save()) {
                $data['via'] = TimesheetLog::VIA_BULK_UPLOAD;
                $timesheet->log($event, $data);
            } else {
                $entry->errors = ["timesheet entry save failure " . json_encode($timesheet->getErrors())];
                $haveError = true;
            }
        }

        return $haveError;
    }
}

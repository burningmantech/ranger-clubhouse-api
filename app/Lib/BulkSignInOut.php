<?php

namespace App\Lib;

use App\Lib\BulkSignInOut\ShiftAction;
use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\Position;
use App\Models\Timesheet;
use App\Models\TimesheetLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Parse and verify Bulk Sign In/Out lines.
 */
class BulkSignInOut
{
    /**
     * HH:MM or HHMM with bounded ranges.
     */
    private const string TIME_REGEXP = '/^([01]?\d|2[0-3]):?([0-5]\d)$/';

    /**
     * MM-DD, MM/DD, MM-DD-YY(YY), MM/DD/YY(YY).
     */
    private const string DATE_REGEXP = '/^(\d{1,2})[-\/](\d{1,2})(?:[-\/](\d{2,4}))?$/';

    private const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * Parse bulk sign in/out lines.
     *
     * @return array{0: array<object>, 1: bool} Tuple of [entries, haveError].
     */
    public static function parse(string $lines): array
    {
        $positionTitles = Position::select('id', 'title')
            ->get()
            ->keyBy(fn(Position $p) => strtolower($p->title));

        $entries = [];
        foreach (explode("\n", $lines) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $columns = array_map('trim', explode(',', $line));
            $entries[] = self::processLine($columns, $positionTitles);
        }

        $haveError = false;
        foreach ($entries as $entry) {
            if (!empty($entry->errors)) {
                $haveError = true;
                break;
            }
        }

        return [$entries, $haveError];
    }

    /**
     * Process a single sign in/out line.
     *
     * Supported formats:
     *   callsign,position
     *   callsign,time
     *   callsign,date,time
     *   callsign,position,time
     *   callsign,position,time,time
     *   callsign,position,date,time
     *   callsign,position,date,time,time
     *   callsign,position,date,time,date,time
     *
     * @param array<int, string> $columns
     * @param Collection<string, Position> $positionTitles keyed by lower-cased title
     */
    public static function processLine(array $columns, Collection $positionTitles): object
    {
        if (empty($columns[0])) {
            return (object)[
                'action' => ShiftAction::Unknown->value,
                'errors' => ['Callsign not specified'],
            ];
        }

        $callsign = $columns[0];
        [$action, $signin, $signout, $positionName, $parseErrors] = self::parseColumns($columns);

        $errors = $parseErrors;
        $person = Person::findByCallsign($callsign);
        $personId = $person?->id;
        if ($person) {
            $callsign = $person->callsign;
        }

        $timesheet = null;
        $timesheetId = null;

        if (!$personId) {
            $errors[] = "callsign '$callsign' not found";
        } elseif (empty($errors)) {
            [$timesheet, $signin, $newErrors] =
                self::validateTimesheetState($personId, $action, $signin, $signout);
            $errors = array_merge($errors, $newErrors);
            $timesheetId = $timesheet?->id;
        }

        [$positionId, $positionName, $positionErrors] = self::resolvePosition(
            $positionName,
            $positionTitles,
            $personId,
            $timesheet,
        );
        $errors = array_merge($errors, $positionErrors);

        return (object)[
            'person_id'    => $personId,
            'callsign'     => $callsign,
            'action'       => $action->value,
            'timesheet_id' => $timesheetId,
            'position'     => $positionName,
            'position_id'  => $positionId,
            'signin'       => $signin !== null ? date(self::DATETIME_FORMAT, $signin) : null,
            'signout'      => $signout !== null ? date(self::DATETIME_FORMAT, $signout) : null,
            'errors'       => $errors,
        ];
    }

    /**
     * Parse the column array into an action, sign in/out timestamps, and position name.
     *
     * @param array<int, string> $columns
     * @return array{0: ShiftAction, 1: ?int, 2: ?int, 3: ?string, 4: array<string>}
     */
    private static function parseColumns(array $columns): array
    {
        $field = $columns[1] ?? '';
        $count = count($columns);
        $errors = [];
        $action = ShiftAction::Unknown;
        $signin = null;
        $signout = null;
        $position = null;

        switch ($count) {
            case 1:
                $errors[] = 'too few columns - need at least a time or position';
                break;

            // callsign,time      - sign out @ time
            // callsign,position  - sign in now w/position
            case 2:
                if (self::isTime($field)) {
                    $action = ShiftAction::Out;
                    $signout = self::parseTime($field, 'sign out', $errors);
                } else {
                    $action = ShiftAction::In;
                    $position = $field;
                }
                break;

            // callsign,date,time     - sign out @ date & time
            // callsign,position,time - sign in @ time w/position
            case 3:
                if (self::isDate($field)) {
                    $action = ShiftAction::Out;
                    $signout = self::parseDateAndTime($field, $columns[2], 'sign out', $errors);
                } else {
                    $action = ShiftAction::In;
                    $position = $field;
                    $signin = self::parseTime($columns[2], 'sign in', $errors);
                }
                break;

            // callsign,position,time,time - sign in/out today
            // callsign,position,date,time - sign in
            case 4:
                $position = $field;
                if (self::isTime($columns[2])) {
                    $action = ShiftAction::InOut;
                    $today = date('n/j/Y');
                    $signin = self::parseDateAndTime($today, $columns[2], 'sign in', $errors);
                    $signout = self::parseDateAndTime($today, $columns[3], 'sign out', $errors);
                    if ($signin !== null && $signout !== null && $signin > $signout) {
                        // graveyard or swing shifts, e.g. 23:45 -> 06:45
                        $signout = strtotime('+1 day', $signout);
                    }
                } else {
                    $action = ShiftAction::In;
                    $signin = self::parseDateAndTime($columns[2], $columns[3], 'sign in', $errors);
                }
                break;

            // callsign,position,date,time,time - sign in & out
            case 5:
                $position = $field;
                $action = ShiftAction::InOut;
                $signin = self::parseDateAndTime($columns[2], $columns[3], 'sign in', $errors);
                $signoutTime = self::parseTime($columns[4], 'sign out', $errors);
                if ($signin !== null && $signoutTime !== null) {
                    $signout = strtotime(date('Y/m/d ', $signin) . date('H:i', $signoutTime));
                    if ($signin > $signout) {
                        // graveyard or swing shifts, e.g. 23:45 -> 06:45
                        $signout = strtotime('+1 day', $signout);
                    }
                }
                break;

            // callsign,position,date,time,date,time - sign in/sign out
            case 6:
                $position = $field;
                $action = ShiftAction::InOut;
                $signin = self::parseDateAndTime($columns[2], $columns[3], 'sign in', $errors);
                $signout = self::parseDateAndTime($columns[4], $columns[5], 'sign out', $errors);
                if ($signin !== null && $signout !== null && $signin >= $signout) {
                    $errors[] = 'sign in time starts on or after sign out';
                }
                break;

            default:
                $errors[] = 'too many columns - format not understood.';
        }

        return [$action, $signin, $signout, $position, $errors];
    }

    /**
     * Validate the person's current timesheet state against the parsed action.
     *
     * Returns the active timesheet (if any), the possibly-updated sign-in time,
     * and any validation errors.
     *
     * @return array{0: ?Timesheet, 1: ?int, 2: array<string>}
     */
    private static function validateTimesheetState(
        int $personId,
        ShiftAction $action,
        ?int $signin,
        ?int $signout,
    ): array {
        $errors = [];
        $timesheet = Timesheet::findPersonOnDuty($personId);

        if ($action === ShiftAction::In && $timesheet) {
            $errors[] = 'is on duty';
        } elseif ($action === ShiftAction::Out) {
            if (!$timesheet) {
                $errors[] = 'is not on duty';
            } elseif ($signout !== null && $timesheet->on_duty->timestamp > $signout) {
                $errors[] = 'sign out is before sign in time';
            }
        }

        if ($action === ShiftAction::InOut) {
            if ($signin !== null && $signout !== null) {
                $checkIn = date(self::DATETIME_FORMAT, $signin);
                $checkOut = date(self::DATETIME_FORMAT, $signout);
                $overlap = Timesheet::findOverlapForPerson($personId, $checkIn, $checkOut);

                if ($overlap) {
                    $errors[] = "overlapping or duplicate timesheet."
                        . " Position {$overlap->position->title}"
                        . " Start {$overlap->on_duty} End {$overlap->off_duty}";
                }
            }
        } elseif ($timesheet) {
            $signin = $timesheet->on_duty->timestamp;
        }

        return [$timesheet, $signin, $errors];
    }

    /**
     * Resolve the position name to an id and canonical title, and check the
     * person holds that position. When no position is given but the person is
     * on duty, fall back to the timesheet's current position.
     *
     * @param Collection<string, Position> $positionTitles
     * @return array{0: ?int, 1: ?string, 2: array<string>}
     */
    private static function resolvePosition(
        ?string $position,
        Collection $positionTitles,
        ?int $personId,
        ?Timesheet $timesheet,
    ): array {
        $errors = [];

        if ($position !== null && $position !== '') {
            $title = strtolower($position);
            $found = $positionTitles->get($title);
            if ($found) {
                if ($personId && !PersonPosition::havePosition($personId, $found->id)) {
                    $errors[] = "does not hold the position '{$found->title}'";
                }
                return [$found->id, $found->title, $errors];
            }

            $errors[] = "position '$position' not found";
            return [null, $position, $errors];
        }

        if ($timesheet) {
            return [$timesheet->position_id, $timesheet->position->title, $errors];
        }

        return [null, null, $errors];
    }

    /**
     * Does the value look like a time? (HH:MM or HHMM, valid ranges).
     */
    public static function isTime(string $value): bool
    {
        if ($value === '007') {
            // The 007 position, not a time.
            return false;
        }
        return (bool)preg_match(self::TIME_REGEXP, $value);
    }

    /**
     * Does the value look like a date? (MM/DD with optional year).
     */
    public static function isDate(string $value): bool
    {
        if ($value === '007') {
            // The 007 position again.
            return false;
        }
        return (bool)preg_match(self::DATE_REGEXP, $value);
    }

    /**
     * Parse a time string into a timestamp (today's date).
     *
     * @param array<string> $errors populated by reference on failure
     */
    public static function parseTime(string $value, string $name, array &$errors): ?int
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

    /**
     * Parse a date + time pair into a timestamp.
     *
     * @param array<string> $errors populated by reference on failure
     */
    public static function parseDateAndTime(string $date, string $time, string $name, array &$errors): ?int
    {
        if (!preg_match(self::DATE_REGEXP, $date, $dateMatch)) {
            $errors[] = "$name has an invalid date [$date]";
            return null;
        }

        $year = $dateMatch[3] ?? null;
        if ($year !== null) {
            if (strlen($year) == 2) {
                $year = '20' . $year;
            }
        } else {
            $year = current_year();
        }

        $month = (int)$dateMatch[1];
        $day = (int)$dateMatch[2];

        if (!checkdate($month, $day, (int)$year)) {
            $errors[] = "$name has an invalid date [$date]";
            return null;
        }

        if (!preg_match(self::TIME_REGEXP, $time, $timeMatch)) {
            $errors[] = "$name has an invalid time [$time]";
            return null;
        }

        $dateString = sprintf('%04d/%02d/%02d %s:%s', $year, $month, $day, $timeMatch[1], $timeMatch[2]);
        $dateTime = strtotime($dateString);
        if ($dateTime === false) {
            $errors[] = "$name has an invalid date and/or time [$dateString]";
            return null;
        }

        return $dateTime;
    }

    /**
     * Persist the parsed entries. Skips entries with parse errors or unknown actions.
     *
     * @param array<object> $entries
     * @return bool true if any persistence error occurred
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function process(array $entries, int $userId): bool
    {
        $haveError = false;
        DB::transaction(function () use ($entries, &$haveError) {
            foreach ($entries as $entry) {
                if (!empty($entry->errors) || $entry->action === ShiftAction::Unknown->value) {
                    continue;
                }
                if (!self::persistEntry($entry)) {
                    $haveError = true;
                }
            }
        });

        return $haveError;
    }

    /**
     * Persist a single entry. Returns false on failure (with errors recorded on the entry).
     */
    private static function persistEntry(object $entry): bool
    {
        $personId = $entry->person_id;
        $positionId = $entry->position_id;
        $signin = $entry->signin;
        $signout = $entry->signout;

        $data = [];
        $event = null;
        $timesheet = null;

        switch ($entry->action) {
            case ShiftAction::InOut->value:
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

            case ShiftAction::In->value:
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
                    'on_duty' => (string)$signin,
                ];
                break;

            case ShiftAction::Out->value:
                $timesheetId = $entry->timesheet_id;
                $timesheet = Timesheet::find($timesheetId);
                if (!$timesheet) {
                    $entry->errors = ["cannot find timesheet id=[$timesheetId]?"];
                    return false;
                }
                if (empty($signout)) {
                    $signout = now();
                }
                $timesheet->off_duty = $signout;
                $event = TimesheetLog::SIGNOFF;
                $data = ['off_duty' => (string)$signout];
                break;
        }

        $timesheet->auditReason = 'bulk sign in/out';
        if (!$timesheet->save()) {
            $entry->errors = ['timesheet entry save failure ' . json_encode($timesheet->getErrors())];
            return false;
        }

        $data['via'] = TimesheetLog::VIA_BULK_UPLOAD;
        $timesheet->log($event, $data);
        return true;
    }
}

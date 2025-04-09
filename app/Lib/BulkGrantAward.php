<?php

namespace App\Lib;

use App\Models\Award;
use App\Models\ErrorLog;
use App\Models\Person;
use App\Models\PersonAward;
use App\Models\Position;
use App\Models\Team;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BulkGrantAward
{

    /**
     * Bulk grant a list of callsigns to give an award to
     *
     * @param string $callsigns
     * @param bool $commit
     * @return array
     */

    public static function upload(string $callsigns, bool $commit): array
    {
        $lines = explode("\n", str_replace("\r", "", $callsigns));
        $callsigns = [];
        $records = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $columns = explode(',', $line);
            $callsign = trim($columns[0]);

            $record = (object)[
                'awards' => [],
                'callsign' => $callsign,
                'columns' => $columns,
                'error' => null,
                'title' => '',
                'type' => '',
                'year' => [],
            ];

            $records[] = $record;

            if (empty($callsign)) {
                $record->error = 'No callsign entered. There might be an extra comma (,) at the beginning of the line.';
                continue;
            }

            $callsigns[] = $callsign;
        }

        if (empty($callsigns)) {
            return $records;
        }

        $people = Person::findAllByCallsigns($callsigns);

        foreach ($records as $record) {
            if ($record->error) {
                continue;
            }

            $person = $people[Person::normalizeCallsign($record->callsign)] ?? null;
            if (!$person) {
                $record->error = 'Callsign not found';
                continue;
            }

            $personId = $person->id;
            $record->id = $personId;
            $record->callsign = $person->callsign;
            $record->status = $person->status;

            $columns = $record->columns;
            $type = $columns[1] ?? '';
            if (empty($type)) {
                $record->error = 'No service type given';
                continue;
            }

            if ($type != 'team' && $type != 'award' && $type != 'position') {
                $record->error = 'Type is neither award, position, or team.';
                continue;
            }

            $title = $columns[2] ?? '';
            if (empty($title)) {
                $record->error = 'No team/award title given';
                continue;
            }

            $teamId = null;
            $awardId = null;
            $award = null;
            $team = null;
            $positionId = null;
            $position = null;

            switch ($type) {
                case 'team':
                    $team = Team::findBytitle($title);
                    if (!$team) {
                        $record->error = 'Team "' . $title . '" not found';
                        continue 2;
                    }

                    if (!$team->isAwardsEligible()) {
                        $record->error = 'Team "' . $title . '" is not set for award eligibility';
                        continue 2;
                    }
                    $teamId = $team->id;
                    $record->title = $team->title;
                    break;

                case 'position':
                    $position = Position::findBytitle($title);
                    if (!$position) {
                        $record->error = 'Position "' . $title . '" not found';
                        continue 2;
                    }
                    if (!$position->awards_eligible) {
                        $record->error = 'Position "' . $title . '" is not set for award eligibility.';
                        continue 2;
                    }
                    $positionId = $position->id;
                    $record->title = $position->title;
                    break;

                default:
                    $award = Award::findBytitle($title);
                    if (!$award) {
                        $record->error = 'Special award "' . $title . '" not found';
                        continue 2;
                    }
                    $awardId = $award->id;
                    $record->title = $award->title;
                    break;
            }


            if (count($columns) < 4) {
                $record->error = 'No year(s) given';
                continue;
            }

            $serviceAwards = [];
            for ($idx = 3; $idx < count($columns); $idx++) {
                $years = $columns[$idx] ?? null;
                if (empty($years)) {
                    $record->error = 'No years given. Perhaps an extra comma is to blame.';
                    continue 2;
                }

                $range = explode('-', $years);
                if (count($range) > 2) {
                    $record->error = 'Years range has too many dashes.';
                    continue 2;
                }

                $start = trim($range[0]);
                if (!self::validateYear($record, $start, 'year')) {
                    continue 2;
                }

                if (isset($range[1])) {
                    $end = trim($range[1]);
                    if (!self::validateYear($record, $end, 'ending year')) {
                        continue 2;
                    }

                    if ($start > $end) {
                        $record->error = 'Start year is after ending year';
                        continue 2;
                    }
                } else {
                    $end = $start;
                }

                $start = (int)$start;
                $end = (int)$end;
                $record->type = $type;
                for ($year = $start; $year <= $end; $year++) {
                    $record->years[] = $year;
                    if ($team) {
                        if (PersonAward::haveTeamAward($personId, $teamId, $year)) {
                            $record->error = 'Team award ' . $team->title . ' for ' . $year . ' already exists';
                            continue 3;
                        }
                    } else if ($position) {
                        if (PersonAward::havePositionAward($personId, $positionId, $year)) {
                            $record->error = 'Position award ' . $position->title . ' for ' . $year . ' already exists';
                            continue 3;
                        }
                    } else {
                        if (PersonAward::haveServiceAward($personId, $awardId, $year)) {
                            $record->error = 'Service award ' . $award->title . ' for ' . $year . ' already exists';
                            continue 3;
                        }
                    }

                    $serviceAward = new PersonAward([
                        'person_id' => $personId,
                        'award_id' => $awardId,
                        'team_id' => $teamId,
                        'position_id' => $positionId,
                        'year' => $year,
                        'notes' => 'bulk granted by ' . Auth::user()?->callsign,
                    ]);

                    $serviceAwards[] = $serviceAward;
                }
            }

            $record->awards = $serviceAwards;

            if (empty($serviceAwards)) {
                // Should never happen.
                $record->error = 'A bug? All the fields have been validated. Yet no awards were found?';
                continue;
            }

            if ($commit) {
                DB::beginTransaction();
                foreach ($serviceAwards as $serviceAward) {
                    try {
                        if (!$serviceAward->save()) {
                            DB::rollBack();
                            foreach ($serviceAward->getErrors() as $column => $errors) {
                                $record->error = $column . ': ' . implode(' / ', $errors);
                            }
                            continue 2;
                        }
                    } catch (QueryException $e) {
                        DB::rollBack();
                        $record->error = 'A database error occurred.';
                        ErrorLog::recordException($e, 'person-award-create-failure', ['person-award' => $record]);
                        continue 2;
                    }
                }
                DB::commit();
            }
        }

        return $records;
    }

    public static function validateYear($record, $year, string $label): bool
    {
        if (empty($year)) {
            $record->error = $label . ' is blank. Perhaps an extra comma is to blame.';
            return false;
        }

        if (!is_numeric($year)) {
            $record->error = 'Year is not an integer';
            return false;
        }

        $year = (int)$year;
        if ($year < PersonAward::FIRST_YEAR_PERMITTED) {
            $record->error = $label . ' ' . $year . ' is before ' . PersonAward::FIRST_YEAR_PERMITTED;
            return false;
        }

        $currentYear = current_year();
        if ($year > $currentYear) {
            $record->error = 'Year ' . $year . ' is in the future. Current year is only ' . $currentYear;
            return false;
        }

        return true;
    }
}
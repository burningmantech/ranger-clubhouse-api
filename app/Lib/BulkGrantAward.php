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
use stdClass;

class BulkGrantAward
{
    private const int COL_CALLSIGN = 0;
    private const int COL_TYPE = 1;
    private const int COL_TITLE = 2;
    private const int COL_SERVICE_YEAR_FLAG = 3;
    private const int COL_YEARS_START = 4;

    private const string TYPE_TEAM = 'team';
    private const string TYPE_POSITION = 'position';
    private const string TYPE_AWARD = 'award';
    private const array VALID_TYPES = [self::TYPE_TEAM, self::TYPE_POSITION, self::TYPE_AWARD];

    /**
     * Bulk grant awards to a list of callsigns.
     *
     * Input format (CSV per line):
     *   callsign,type,title,serviceYearFlag(y/n),year[,year...]
     * Year columns may be ranges like "2020-2023".
     *
     * @param string $callsigns Newline-separated CSV rows.
     * @param bool $commit When true, persist; otherwise dry-run validation.
     * @return array<int,stdClass> One record per input row, with error/title/years/etc.
     */
    public static function upload(string $callsigns, bool $commit): array
    {
        $records = self::parseRecords($callsigns);
        $lookupCallsigns = self::collectValidCallsigns($records);

        if (empty($lookupCallsigns)) {
            return $records;
        }

        $people = Person::findAllByCallsigns($lookupCallsigns);
        $grantedBy = 'bulk granted by ' . Auth::user()?->callsign;
        $currentYear = current_year();

        $pendingByPerson = [];

        foreach ($records as $record) {
            if ($record->error) {
                continue;
            }

            $pending = self::processRecord($record, $people, $grantedBy, $currentYear);
            if ($pending !== null) {
                $pendingByPerson[$record->id] = array_merge(
                    $pendingByPerson[$record->id] ?? [],
                    $pending,
                );
            }
        }

        if ($commit) {
            self::persist($records, $pendingByPerson);
        }

        return $records;
    }

    /**
     * @return array<int,stdClass>
     */
    private static function parseRecords(string $callsigns): array
    {
        $lines = explode("\n", str_replace("\r", "", $callsigns));
        $records = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $columns = explode(',', $line);
            $callsign = trim($columns[self::COL_CALLSIGN]);

            $record = (object)[
                'callsign' => $callsign,
                'columns' => $columns,
                'error' => null,
                'title' => '',
                'type' => '',
                'years' => [],
            ];

            if (empty($callsign)) {
                $record->error = 'No callsign entered. There might be an extra comma (,) at the beginning of the line.';
            }

            $records[] = $record;
        }

        return $records;
    }

    /**
     * @param array<int,stdClass> $records
     * @return array<int,string>
     */
    private static function collectValidCallsigns(array $records): array
    {
        $callsigns = [];
        foreach ($records as $record) {
            if (!$record->error) {
                $callsigns[] = $record->callsign;
            }
        }
        return $callsigns;
    }

    /**
     * Validate and build pending PersonAward instances for one record.
     *
     * @return array<int,PersonAward>|null Null if the record errored; otherwise the awards to save.
     */
    private static function processRecord(
        stdClass $record,
        array $people,
        string $grantedBy,
        int $currentYear,
    ): ?array {
        $person = $people[Person::normalizeCallsign($record->callsign)] ?? null;
        if (!$person) {
            $record->error = 'Callsign not found';
            return null;
        }

        $record->id = $person->id;
        $record->callsign = $person->callsign;
        $record->status = $person->status;

        $columns = $record->columns;
        $type = $columns[self::COL_TYPE] ?? '';
        if (empty($type)) {
            $record->error = 'No service type given';
            return null;
        }
        if (!in_array($type, self::VALID_TYPES, true)) {
            $record->error = 'Type is neither award, position, or team.';
            return null;
        }
        $record->type = $type;

        $title = $columns[self::COL_TITLE] ?? '';
        if (empty($title)) {
            $record->error = 'No team/award title given';
            return null;
        }

        $target = self::resolveTarget($type, $title, $record);
        if ($target === null) {
            return null;
        }
        $record->title = $target->title;

        if (count($columns) < 5) {
            $record->error = 'No award year(s) given.';
            return null;
        }

        $serviceYearFlag = strtolower($columns[self::COL_SERVICE_YEAR_FLAG]);
        if ($serviceYearFlag !== 'y' && $serviceYearFlag !== 'n') {
            $record->error = 'Award year indicator "' . $serviceYearFlag . '" is neither y nor n';
            return null;
        }
        $grantServiceYear = $serviceYearFlag === 'y';
        $record->is_service_year = $grantServiceYear;

        $years = self::parseYears($record, $currentYear);
        if ($years === null) {
            return null;
        }

        $pending = [];
        foreach ($years as $year) {
            if (self::alreadyHasAward($target, $person->id, $year)) {
                $record->error = ucfirst($type) . ' award ' . $target->title
                    . ' for ' . $year . ' already exists';
                return null;
            }

            $record->years[] = $year;
            $pending[] = new PersonAward([
                'person_id' => $person->id,
                'award_id' => $target->awardId,
                'team_id' => $target->teamId,
                'position_id' => $target->positionId,
                'awards_grants_service_year' => $grantServiceYear,
                'year' => $year,
                'notes' => $grantedBy,
            ]);
        }

        if (empty($pending)) {
            $record->error = 'A bug? All the fields have been validated. Yet no awards were found?';
            return null;
        }

        return $pending;
    }

    private static function resolveTarget(string $type, string $title, stdClass $record): ?stdClass
    {
        $result = (object)[
            'title' => '',
            'awardId' => null,
            'teamId' => null,
            'positionId' => null,
        ];

        switch ($type) {
            case self::TYPE_TEAM:
                $team = Team::findBytitle($title);
                if (!$team) {
                    $record->error = 'Team "' . $title . '" not found';
                    return null;
                }
                if (!$team->awards_eligible) {
                    $record->error = 'Team "' . $title . '" is not set for award eligibility';
                    return null;
                }
                $result->title = $team->title;
                $result->teamId = $team->id;
                return $result;

            case self::TYPE_POSITION:
                $position = Position::findBytitle($title);
                if (!$position) {
                    $record->error = 'Position "' . $title . '" not found';
                    return null;
                }
                if (!$position->awards_eligible) {
                    $record->error = 'Position "' . $title . '" is not set for award eligibility.';
                    return null;
                }
                $result->title = $position->title;
                $result->positionId = $position->id;
                return $result;

            case self::TYPE_AWARD:
                $award = Award::findBytitle($title);
                if (!$award) {
                    $record->error = 'Special award "' . $title . '" not found';
                    return null;
                }
                $result->title = $award->title;
                $result->awardId = $award->id;
                return $result;
        }

        return null;
    }

    /**
     * Expand the year columns into a flat list, validating each.
     *
     * @return array<int,int>|null
     */
    private static function parseYears(stdClass $record, int $currentYear): ?array
    {
        $years = [];
        $count = count($record->columns);

        for ($idx = self::COL_YEARS_START; $idx < $count; $idx++) {
            $raw = $record->columns[$idx] ?? null;
            if (empty($raw)) {
                $record->error = 'No years given. Perhaps an extra comma is to blame.';
                return null;
            }

            $range = explode('-', $raw);
            if (count($range) > 2) {
                $record->error = 'Years range has too many dashes.';
                return null;
            }

            $start = trim($range[0]);
            if (!self::validateYear($record, $start, 'year', $currentYear)) {
                return null;
            }

            $end = isset($range[1]) ? trim($range[1]) : $start;
            if (isset($range[1]) && !self::validateYear($record, $end, 'ending year', $currentYear)) {
                return null;
            }

            if ($start > $end) {
                $record->error = 'Start year is after ending year';
                return null;
            }

            for ($year = (int)$start; $year <= (int)$end; $year++) {
                $years[] = $year;
            }
        }

        return $years;
    }

    private static function alreadyHasAward(stdClass $target, int $personId, int $year): bool
    {
        if ($target->teamId !== null) {
            return PersonAward::haveTeamAward($personId, $target->teamId, $year);
        }
        if ($target->positionId !== null) {
            return PersonAward::havePositionAward($personId, $target->positionId, $year);
        }
        return PersonAward::haveServiceAward($personId, $target->awardId, $year);
    }

    /**
     * @param array<int,stdClass> $records
     * @param array<int,array<int,PersonAward>> $pendingByPerson Keyed by person id.
     */
    private static function persist(array $records, array $pendingByPerson): void
    {
        $recordsByPerson = [];
        foreach ($records as $record) {
            if (!$record->error && isset($record->id)) {
                $recordsByPerson[$record->id] = $record;
            }
        }

        foreach ($pendingByPerson as $personId => $awards) {
            $record = $recordsByPerson[$personId] ?? null;
            if ($record === null) {
                continue;
            }

            DB::beginTransaction();
            try {
                foreach ($awards as $award) {
                    if (!$award->save()) {
                        DB::rollBack();
                        $errors = [];
                        foreach ($award->getErrors() as $column => $columnErrors) {
                            $errors[] = $column . ': ' . implode(' / ', $columnErrors);
                        }
                        $record->error = implode('; ', $errors);
                        continue 2;
                    }
                }
                DB::commit();
                YearsManagement::updateYearsOfAwards($personId);
            } catch (QueryException $e) {
                DB::rollBack();
                $record->error = 'A database error occurred.';
                ErrorLog::recordException($e, 'person-award-create-failure', ['person-award' => $record]);
            }
        }
    }

    public static function validateYear(stdClass $record, string $year, string $label, ?int $currentYear = null): bool
    {
        if (empty($year)) {
            $record->error = $label . ' is blank. Perhaps an extra comma is to blame.';
            return false;
        }

        if (!is_numeric($year)) {
            $record->error = 'Year is not a number';
            return false;
        }

        $intYear = (int)$year;
        if ($intYear < PersonAward::FIRST_YEAR_PERMITTED) {
            $record->error = $label . ' ' . $intYear . ' is before ' . PersonAward::FIRST_YEAR_PERMITTED;
            return false;
        }

        $currentYear ??= current_year();
        if ($intYear > $currentYear) {
            $record->error = 'Year ' . $intYear . ' is in the future. Current year is only ' . $currentYear;
            return false;
        }

        return true;
    }
}

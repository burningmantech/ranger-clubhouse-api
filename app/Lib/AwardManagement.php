<?php

namespace App\Lib;

use App\Models\Person;
use App\Models\PersonAward;
use App\Models\PersonTeamLog;
use App\Models\Timesheet;
use App\Models\TrainerStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AwardManagement
{
    // The years the logs came online.
    const int TEAM_LOG_START = 2022;

    public static function rebuildAll(): void
    {
        /*
         TODO: Revisit when ready for automated grants. Existing awards might be getting zapped.

             $positionIds = self::retrievePositionIds();
              $teamsByPerson = self::retrieveTeamLogs(null)->groupBy('person_id');
              $awardsByPerson = PersonAward::all()->groupBy('person_id');

              if ($positionIds) {
                  $taughtYears = self::retrieveTrainerRecords(null, $positionIds)->groupBy('person_id');
                  $timesheetsByPerson = Timesheet::select('person_id', 'on_duty', 'position_id')
                      ->whereIn('position_id', $positionIds)
                      ->with('position:id,awards_grants_service_year')
                      ->get()
                      ->groupBy('person_id');
              } else {
                  $taughtYears = null;
                  $timesheetsByPerson = null;
              }

              $personIds = $teamsByPerson->keys()->toArray();
              $personIds = array_merge($personIds, $awardsByPerson->keys()->toArray());
              if ($taughtYears) {
                  $personIds = array_merge($personIds, $taughtYears->keys()->toArray());
              }


              $personIds = array_unique($personIds);

              $people = Person::whereIn('status', Person::ACTIVE_STATUSES)
                  ->whereIntegerInRaw('id', $personIds)
                  ->get();

              foreach ($people as $person) {
                  $personId = $person->id;
                  self::reconstructAwards($person,
                      $awardsByPerson->get($personId) ?: collect([]),
                      $positionIds,
                      $taughtYears?->get($personId),
                      $teamsByPerson->get($personId),
                      $timesheetsByPerson?->get($personId)
                  );
              }
       */
    }

    public static function rebuildForPersonId(int $personId, bool $savePerson = true): void
    {
        /*
         TODO: Revisit when ready for automated grants. Existing awards might be getting zapped.
        $person = Person::find($personId);
        $awards = PersonAward::where('person_id', $personId)->get();
        $positionIds = self::retrievePositionIds();
        $teams = self::retrieveTeamLogs($personId);

        if ($positionIds) {
            $taughtYears = self::retrieveTrainerRecords($personId, $positionIds);
            // ... find what eligible timesheets were worked.
            $timesheets = Timesheet::select('on_duty', 'position_id')
                ->where('person_id', $personId)
                ->whereIn('position_id', $positionIds)
                ->with('position:id,awards_grants_service_year')
                ->get();
        } else {
            $taughtYears = null;
            $timesheets = null;
        }

        self::reconstructAwards($person, $awards, $positionIds, $taughtYears, $teams, $timesheets, $savePerson);
        */
    }

    public static function reconstructAwards(
        Person      $person,
        ?Collection $awards,
        ?Collection $positionIds,
        ?Collection $taughtYears,
        ?Collection $teams,
        ?Collection $timesheets,
        bool        $savePerson = true,
    ): void
    {
        $currentYear = current_year();
        $personId = $person->id;

        $existingTeamYears = [];
        $existingPositionYears = [];
        $existingSpecialYears = [];
        if ($awards) {
            foreach ($awards as $award) {
                if ($award->team_id) {
                    if ($award->year < self::TEAM_LOG_START) {
                        continue;
                    }
                    $existingTeamYears[$award->team_id][$award->year] = $award;
                } else if ($award->position_id) {
                    $existingPositionYears[$award->position_id][$award->year] = $award;
                } else {
                    $existingSpecialYears[] = $award->year;
                }
            }
        }

        $years = array_unique($existingSpecialYears);

        if ($teams) {
            foreach ($teams as $team) {
                $endYear = $team->left_on ? $team->left_on->year : $currentYear;
                $teamId = $team->team_id;
                for ($year = $team->joined_on->year; $year <= $endYear; $year++) {
                    $years[] = $year;
                    self::removeYear($year, $teamId, $existingTeamYears);
                    self::createIfMissing($personId, 'team_id', $teamId, $year, $awards,
                        $team->team->awards_grants_service_year);
                }
            }
        }

        /*
        foreach ($existingTeamYears as $teamId => $teamYears) {
            foreach ($teamYears as $year => $award) {
                $award->auditReason = 'no team history found';
                $award->bulkUpdate = true;
                $award->delete();
            }
        }
*/

        // Find the eligible positions
        if ($positionIds) {
            if ($taughtYears) {
                // Inspect trainer records.
                foreach ($taughtYears as $taughtYear) {
                    $year = $taughtYear->begins_year;
                    $years[] = $year;
                    self::removeYear($year, $taughtYear->position_id, $existingPositionYears);
                    self::createIfMissing($personId, 'position_id', $taughtYear->position_id, $year, $awards,
                        $taughtYear->awards_grants_service_year
                    );
                }
            }

            $existingYears = [];
            if ($timesheets) {
                foreach ($timesheets as $timesheet) {
                    $year = $timesheet->on_duty->year;
                    $positionId = $timesheet->position_id;
                    self::removeYear($year, $positionId, $existingPositionYears);
                    if (isset($existingYears[$positionId]) && in_array($year, $existingYears[$positionId])) {
                        continue;
                    }
                    $years[] = $year;
                    self::createIfMissing($personId, 'position_id', $timesheet->position_id, $year, $awards,
                        $timesheet->position->awards_grants_service_year);
                    $existingYears[$positionId][] = $year;
                }
            }
        }

        /*
        foreach ($existingPositionYears as $positionId => $positionYears) {
            foreach ($positionYears as $year => $award) {
                $award->auditReason = 'No backing trainer status or timesheet found';
                $award->delete();
            }
        }*/


        $years = array_unique($years);
        sort($years);

        $person->years_of_awards = $years;
        if ($savePerson) {
            YearsManagement::savePerson($person, 'awards rebuild');
        }
    }

    public static function createIfMissing(int         $personId,
                                           string      $column,
                                           int         $value,
                                           int         $year,
                                           ?Collection $awards,
                                           bool        $isServiceYear): void
    {
        if ($awards?->contains(fn($a) => $a->person_id === $personId && $a->{$column} === $value && $a->year == $year)) {
            return;
        }

        $pa = new PersonAward([
            'person_id' => $personId,
            $column => $value,
            'year' => $year,
            'awards_grants_service_year' => $isServiceYear,
            'notes' => 'automatic creation'
        ]);
        $pa->bulkUpdate = true;
        $pa->auditReason = 'automatic creation';
        $pa->save();
    }

    public static function removeYear($year, int $entityId, &$existing): void
    {
        if (isset($existing[$entityId][$year])) {
            unset($existing[$entityId][$year]);
        }
    }

    public static function retrievePositionIds(): ?Collection
    {
        $positionIds = DB::table('position')->where('awards_auto_grant', true)->pluck('id');
        return $positionIds->isNotEmpty() ? $positionIds : null;
    }

    public static function retrieveTrainerRecords(?int $personId, $positionIds): ?Collection
    {
        $sql = TrainerStatus::select(
            'trainer_status.person_id',
            'slot.position_id',
            'slot.begins_year',
            'slot.position_id',
            'position.awards_grants_service_year'
        )->join('slot', 'trainer_status.trainer_slot_id', '=', 'slot.id')
            ->join('position', 'position.id', '=', 'slot.position_id')
            ->whereIn('position.id', $positionIds)
            ->where('trainer_status.status', TrainerStatus::ATTENDED);

        if ($personId) {
            $sql->where('trainer_status.person_id', $personId);
        }

        return $sql->get();
    }

    public static function retrieveTeamLogs(?int $personId): Collection
    {
        $sql = PersonTeamLog::select('person_team_log.*')
            ->join('team', 'team.id', '=', 'person_team_log.team_id')
            ->where('team.awards_auto_grant', true);

        if ($personId) {
            $sql->where('person_id', $personId);
        }

        return $sql->get();
    }
}
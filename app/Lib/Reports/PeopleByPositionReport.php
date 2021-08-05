<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\Position;
use Illuminate\Support\Facades\DB;

class PeopleByPositionReport
{
    /**
     * Lists people who have (or do not have, for "all rangers" cases) a position.
     * Parameters: onPlaya - If true, only people on site will be returned.
     * Result: {positions: […], people: […]}.
     * Position objects are {id, title, type, all_rangers, new_user_eligible, num_people, num_on_site, personIds,
     * missingPersonIds} with the num fields counting the number of people with the position assigned, the personId
     * felds containing lists of people IDs who have the position and the other fields taken directly from the
     * position table.
     * Person objects are {id, callsign, status, on_site} taken from the person table.  personIds and missingPersonIds
     * from position objects can be looked up in the people list (not denormalized so as to save space).
     *
     * @param bool $onPlaya
     * @return array
     */

    public static function execute(bool $onPlaya): array
    {
        $positions = array();
        $allPeople = array();
        // Statuses that qualify for "all rangers"
        $rangerStatuses = array(Person::ACTIVE, Person::INACTIVE, Person::INACTIVE_EXTENSION, Person::SUSPENDED);

        // Statuses that do not qualify for "new user eligible"
        $nonTrainingStatuses = array(Person::DECEASED, Person::DISMISSED, Person::RESIGNED, Person::UBERBONKED);

        $positionQuery = Position::select(
            'id',
            'title',
            'active',
            'type',
            'new_user_eligible',
            'all_rangers',
            DB::raw("(SELECT COUNT(*) FROM person_position WHERE position_id = position.id) AS num_people"),
            DB::raw("(SELECT COUNT(*) FROM person_position"
                . " INNER JOIN person ON person.id = person_position.person_id"
                . " WHERE position_id = position.id AND person.on_site) AS num_on_site")
        );
        foreach ($positionQuery->get() as $pos) {
            $position = $pos->toArray();
            if (!$position['new_user_eligible'] && !$position['all_rangers']) { // show people with the position
                $pps = PersonPosition::where('position_id', $pos->id);
                if ($onPlaya) {
                    $pps = $pps->join('person', 'person.id', '=', 'person_position.person_id')
                        ->where('person.on_site', true);
                }
                $personIds = $pps->pluck('person_id')->toArray();
                $position['personIds'] = $personIds;
            } else { // show Rangers (or all people) who don't have the position
                if ($position['new_user_eligible']) {
                    $missingPeopleQuery = Person::whereNotIn('status', $nonTrainingStatuses);
                } elseif ($position['all_rangers']) {
                    $missingPeopleQuery = Person::whereIn('status', $rangerStatuses);
                    // also show non-Rangers who have the position, suspiciously
                    $nonRangersQuery =
                        PersonPosition::where('position_id', $pos->id)
                            ->join('person', 'person.id', '=', 'person_position.person_id')
                            ->whereNotIn('person.status', $rangerStatuses);
                    if ($onPlaya) {
                        $nonRangersQuery = $nonRangersQuery->where('person.on_site', 'true');
                    }
                    $suspiciousPersonIds = $nonRangersQuery->pluck('person_id')->toArray();
                    if (!empty($suspiciousPersonIds)) {
                        $position['personIds'] = $suspiciousPersonIds;
                        foreach ($suspiciousPersonIds as $pid) {
                            $allPeople[$pid] = true;
                        }
                    }
                }
                if ($onPlaya) {
                    $missingPeopleQuery = $missingPeopleQuery->where('on_site', true);
                }
                $personIds = $missingPeopleQuery
                    ->whereNotExists(function ($query) use ($position) {
                        $query->select(DB::raw(1))
                            ->from('person_position')
                            ->where('position_id', $position['id'])
                            ->whereRaw('person_position.person_id = person.id');
                    })->pluck('id')->toArray();
                $position['missingPersonIds'] = $personIds;
            }
            foreach ($personIds as $pid) {
                $allPeople[$pid] = true;
            }
            $positions[] = $position;
        }
        $people = DB::table('person')
            ->whereIn('id', array_keys($allPeople))
            ->select('id', 'callsign', 'status', 'on_site')
            ->get();

        return [
            'positions' => $positions,
            'people' => $people
        ];
    }
}
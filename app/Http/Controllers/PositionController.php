<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\Position;
use App\Http\Controllers\ApiController;

use DB;
use Illuminate\Http\Request;

class PositionController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $params = request()->validate([
           'type'   => 'sometimes|string'
        ]);

        //$this->authorize('view');
        return $this->success(Position::findForQuery($params), null);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->authorize('store', Position::class);

        $position = new Position;
        $this->fromRest($position);

        if ($position->save()) {
            return $this->success($position);
        }

        return $this->restError($position);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Position  $position
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Position $position)
    {
        return $this->success($position);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Position  $position
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Position $position)
    {
        $this->authorize('update', Position::class);
        $this->fromRest($position);

        if ($position->save()) {
            return $this->success($position);
        }

        return $this->restError($position);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Position  $position
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Position $position)
    {
        $this->authorize('delete', Position::class);
        $position->delete();
        return $this->restDeleteSuccess();
    }

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
     */
    public function peopleByPosition(Request $request)
    {
        $this->authorize('index', Person::class);
        $params = request()->validate([
            'onPlaya' => 'sometimes|boolean'
        ]);

        $onPlaya = $params['onPlaya'] ?? false;

        $positions = array();
        $allPeople = array();
        // Statuses that qualify for "all rangers"
        $rangerStatuses = array(Person::ACTIVE, Person::INACTIVE, Person::INACTIVE_EXTENSION, Person::SUSPENDED);
        // Statuses that do not qualify for "new user eligible"
        $nonTrainingStatuses = array(Person::DECEASED, Person::DISMISSED, Person::RESIGNED, Person::UBERBONKED);
        $positionQuery = Position::select(
            'id',
            'title',
            'type',
            'new_user_eligible',
            'all_rangers',
            DB::raw("(SELECT COUNT(*) FROM person_position WHERE position_id = position.id) AS num_people"),
            DB::raw("(SELECT COUNT(*) FROM person_position"
                ." INNER JOIN person ON person.id = person_position.person_id"
                ." WHERE position_id = position.id AND person.on_site) AS num_on_site")
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
        $people = Person::whereIn('id', array_keys($allPeople))
            ->select('id', 'callsign', 'status', 'on_site')
            ->get();
        return response()->json(array('positions' => $positions, 'people' => $people));
    }

    /**
     * Sandman Qualification Report
     */
    public function sandmanQualifiedReport()
    {
        $this->authorize('sandmanQualified', Position::class);
        return response()->json(Position::retrieveSandPeopleQualifications());
    }

}

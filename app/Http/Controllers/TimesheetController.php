<?php

namespace App\Http\Controllers;

use App\Models\Timesheet;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;

use App\Models\Training;

class TimesheetController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = $request->validate([
            'year'      => 'sometimes|digits:4',
            'person_id' => 'sometimes|numeric'
        ]);

        $this->authorize('index', [ Timesheet::class, $query['person_id'] ]);

        $rows = Timesheet::findForQuery($query);

        if ($rows->isEmpty()) {
            return $this->restError('No timesheets found', 404);
        }

        return $this->success($rows);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $timesheet = new \App\Models\Timesheet;
        $this->fromRest($timesheet);

        $this->authorize('store', [ Timesheet::class, $timesheet->person_id ]);

        if ($timesheet->save()) {
            return $this->success($timesheet);
        }

        return $this->restError($timesheet);
    }

    /**
     * Update the specified resource in storage.
     */

    public function update(Request $request, Timesheet $timesheet)
    {
        $this->authorize('update', $timesheet);

        $this->fromRest($timesheet);
        if ($timesheet->save()) {
            return $this->success($timesheet);
        }

        return $this->restError($timesheet);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Timesheet $timesheet)
    {
        $this->authorize('delete', $timesheet);
        $timesheet->delete();
    }

    /*
     * Start a shift
     */

     public function signin(Request $request)
     {
         $this->authorize('signin', [ Timesheet::class ]);
         $isAdmin = $this->userHasRole(Role::ADMIN);

         $params = request()->validate([
             'person_id'    => 'required|integer',
             'position_id'  => 'required|integer'
         ]);

         $personId = $params['person_id'];
         $positionId = $params['position_id'];

         // confirm person exists
         $person = $this->findPerson($personId);

         if (!PersonPosition::havePosition($personId, $positionId)) {
             throw new \InvalidArgumentException('Person does not hold the position.');
         }

         if (Timesheet::isPersonOnDuty($personId)) {
             throw new \InvalidArgumentException('Person is already on duty.');
         }

         $signonForced = false;
         $required = null;

         if (!Training::isPersonTrained($personId, $positionId, $required))  {
             if ($isAdmin) {
                 $signonForced = true;
             } else {
                 throw new \InvalidArgumentException("Person has not completed '$required' so cannot sign in for a shift.");
             }
         }

         $timesheet = new \App\Models\Timesheet;
         $timesheet->fill($params);
         $timesheet->setOnDutyToNow();
         if ($timesheet->save()) {
             return $this->success($timesheet, [ 'forced' => $signonForced ]);
         }

         return $this->restError($timesheet);
     }
}

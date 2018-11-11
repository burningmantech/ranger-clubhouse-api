<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;

use App\Models\PositionCredit;
use App\Models\Timesheet;
use App\Models\TimesheetLog;
use App\Models\Training;

class TimesheetController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $params = $request->validate([
            'year'      => 'required|digits:4',
            'person_id' => 'required|numeric'
        ]);

        $this->authorize('index', [ Timesheet::class, $params['person_id'] ]);

        $rows = Timesheet::findForQuery($params);

        if ($rows->isEmpty()) {
            return $this->restError('No timesheets found', 404);
        }

        PositionCredit::warmYearCache($params['year'], array_unique($rows->pluck('position_id')->toArray()));

        return $this->success($rows);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $timesheet = new \App\Models\Timesheet;

        $this->fromRest($timesheet);
        $this->authorize('store', $timesheet);

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

        $person = $this->findPerson($timesheet->person_id);

        $this->fromRestFiltered($timesheet);

        $logInfo = [];
        $markedUnconfirmed = false;

        if ($timesheet->isDirty('verified')) {
            if ($timesheet->verified) {
                $timesheet->setVerifiedAtToNow();
                $timesheet->verified_person_id = $this->user->id;
                $logInfo[] = 'verified';
            } else {
                $logInfo[] = 'unverified';
                if ($person->timesheet_confirmed) {
                    $markedUnconfirmed = true;
                }
            }
        }

        if ($timesheet->isDirty('notes')) {
                $logInfo[] = 'note updated';
        }

        if (!$timesheet->save()) {
            return $this->restError($timesheet);
        }

        if (!empty($logInfo)) {
            TimesheetLog::record('review', $person->id, $this->user->id, $timesheet->id, implode(', ', $logInfo));
        }

        if ($markedUnconfirmed) {
            $person->timesheet_confirmed = false;
            $person->saveWithoutValidation();

            TimesheetLog::record('confirmed', $person->id, $this->user->id, $timesheet->id, 'unconfirmed - entry marked incorrect');
        }

        // Load up position title, reviewer callsigns in case of change.
        $timesheet->loadRelationships();

        return $this->success($timesheet);
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

        if (!Training::isPersonTrained($personId, $positionId, $required)) {
            if ($isAdmin) {
                $signonForced = true;
                $trainingTitle = Position::retrieveTitle($required);
            } else {
                throw new \InvalidArgumentException("Person has not completed '$required' so cannot sign in for a shift.");
            }
        }

        $timesheet = new \App\Models\Timesheet;
        $timesheet->fill($params);
        $timesheet->setOnDutyToNow();
        if ($timesheet->save()) {
            $timesheet->loadRelationships();

            TimesheetLog::record('signon',
                    $person->id, $this->user->id, $timesheet->id,
                    ($signonForced ? "forced (not trained $trainingTitle) " : '').
                            $timesheet->position->title." ".(string) $timesheet->on_duty);


            return $this->success($timesheet, [ 'forced' => $signonForced ]);
        }

        return $this->restError($timesheet);
    }

    /*
     * Return information regarding this person if timesheet corrections are
     * enabled, and IF and when they confirmed all their timesheets.
     */

     public function info()
     {
         $params = request()->validate([
             'person_id'    => 'required|integer'
         ]);

         $person = $this->findPerson($params['person_id']);

         return response()->json([
             'info' => [
                 'correction_year'      => config('clubhouse.TimesheetCorrectionYear'),
                 'correction_enabled' => config('clubhouse.TimesheetCorrectionEnable'),
                 'timesheet_confirmed'    => $person->timesheet_confirmed,
                 'timesheet_confirmed_at' => ($person->timesheet_confirmed ? (string) $person->timesheet_confirmed_at : null),
             ]
         ]);
     }
}

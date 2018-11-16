<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;

use App\Helpers\SqlHelper;

use App\Models\Position;
use App\Models\PositionCredit;
use App\Models\Timesheet;
use App\Models\TimesheetLog;
use App\Models\Training;


class TimesheetController extends ApiController
{
    /*
     * Retrieve a list of timesheets for a person and year.
     */
    public function index(Request $request)
    {
        $params = $request->validate([
            'year'      => 'required|digits:4',
            'person_id' => 'required|numeric'
        ]);

        $this->authorize('index', [ Timesheet::class, $params['person_id'] ]);

        $rows = Timesheet::findForQuery($params);

        if (!$rows->isEmpty()) {
            PositionCredit::warmYearCache($params['year'], array_unique($rows->pluck('position_id')->toArray()));
        }

        return $this->success($rows, null, 'timesheet');
    }

    /*
     * Create a new timesheet
     */
    public function store(Request $request)
    {
        $timesheet = new \App\Models\Timesheet;

        $this->fromRest($timesheet);
        $this->authorize('store', $timesheet);

        if ($timesheet->save()) {
            $this->loadRelationships();
            return $this->success($timesheet);
        }

        return $this->restError($timesheet);
    }

    /*
     * Update an existing timesheet
     */

    public function update(Timesheet $timesheet)
    {
        $this->authorize('update', $timesheet);

        $person = $this->findPerson($timesheet->person_id);

        $this->fromRestFiltered($timesheet);

        $reviewInfo = [];
        $updateInfo = [];
        $verifyInfo = [];

        $markedUnconfirmed = false;

        if ($timesheet->isDirty('off_duty')) {
            $updateInfo[] .= 'off duty old '.$timesheet->getOriginal('off_duty').' new '.$timesheet->off_duty;
        }

        if ($timesheet->isDirty('on_duty')) {
            $updateInfo[] .= 'on duty old '.$timesheet->getOriginal('on_duty').' new '.$timesheet->on_duty;
        }

        if ($timesheet->isDirty('position_id')) {
            $updateInfo[] = 'position old '.Position::retrieveTitle($timesheet->getOriginal('position_id'))
                            .' new '.Position::retrieveTitle($timesheet->position_id);

        }

        if ($timesheet->isDirty('verified')) {
            if ($timesheet->verified) {
                $timesheet->setVerifiedAtToNow();
                $timesheet->verified_person_id = $this->user->id;
                $verifyInfo[] = 'verified';
            } else {
                $verifyInfo[] = 'unverified';
                if ($person->timesheet_confirmed) {
                    $markedUnconfirmed = true;
                }
            }
        }

        if ($timesheet->isDirty('notes')) {
            $verifyInfo[] = 'note updated';
        }

        if ($timesheet->isDirty('review_status')) {
            $reviewInfo[] = 'status '.$timesheet->review_status;
        }

        // Update reviewer person if the review status or review notes changed
        if ($timesheet->isDirty('review_status') || $timesheet->isDirty('reviewer_notes')) {
            $timesheet->reviewer_person_id = $this->user->id;
        }

        if (!$timesheet->save()) {
            return $this->restError($timesheet);
        }

        if (!empty($reviewInfo)) {
            TimesheetLog::record('review', $person->id, $this->user->id, $timesheet->id, implode(', ', $reviewInfo));
        }

        if (!empty($updateInfo)) {
            TimesheetLog::record('update', $person->id, $this->user->id, $timesheet->id, implode(', ', $updateInfo));
        }
        if (!empty($verifyInfo)) {
            TimesheetLog::record('verify', $person->id, $this->user->id, $timesheet->id, implode(', ', $verifyInfo));
        }

        if ($markedUnconfirmed) {
            $person->timesheet_confirmed = false;
            $person->saveWithoutValidation();
            TimesheetLog::record('confirmed', $person->id, $this->user->id, null, 'unconfirmed - entry marked incorrect');
        }

        // Load up position title, reviewer callsigns in case of change.
        $timesheet->loadRelationships();

        return $this->success($timesheet);
    }

    /*
     *
     */
    public function destroy(Timesheet $timesheet)
    {
        $this->authorize('destroy', $timesheet);
        $timesheet->delete();

        $positionTitle = Position::retrieveTitle($timesheet->position_id);
        TimesheetLog::record('delete',
                $timesheet->person_id, $this->user->id, $timesheet->id,
                "{$positionTitle} {$timesheet->on_duty} - {$timesheet->off_duty}");

        return $this->restDeleteSuccess();
    }

    /*
     * Start a shift for a person
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

        // Confirm the person is allowed to sign into the position
        if (!PersonPosition::havePosition($personId, $positionId)) {
            throw new \InvalidArgumentException('Person does not hold the position.');
        }

        // they cannot be already on duty.
        if (Timesheet::isPersonOnDuty($personId)) {
            throw new \InvalidArgumentException('Person is already on duty.');
        }

        $signonForced = false;
        $required = null;

        // Are they trained for this position?
        if (!Training::isPersonTrained($personId, $positionId, $required)) {
            if ($isAdmin) {
                $signonForced = true;
                $trainingTitle = Position::retrieveTitle($required);
            } else {
                throw new \InvalidArgumentException("Person has not completed '$required' so cannot sign in for a shift.");
            }
        }

        $timesheet = new Timesheet($params);
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
     * Start a shift for a person
     */

    public function signoff(Timesheet $timesheet)
    {
        $this->authorize('signoff', $timesheet);

        $timesheet->setOffDutyToNow();
        $timesheet->save();
        $timesheet->loadRelationships();
        TimesheetLog::record('signoff',
                $timesheet->person_id, $this->user->id, $timesheet->id,
                $timesheet->position->title." ".(string) $timesheet->off_duty);
        return $this->success($timesheet);
    }


    /*
     * Return information on timesheet corrections AND the current timesheet
     * confirmation status for a person.
     */

     public function info()
     {
         $params = request()->validate([
             'person_id'    => 'required|integer'
         ]);

         $person = $this->findPerson($params['person_id']);

         return response()->json([
             'info' => [
                 'correction_year'     => config('clubhouse.TimesheetCorrectionYear'),
                 'correction_enabled'  => config('clubhouse.TimesheetCorrectionEnable'),
                 'timesheet_confirmed' => (int) $person->timesheet_confirmed,
                 'timesheet_confirmed_at' => ($person->timesheet_confirmed ? (string) $person->timesheet_confirmed_at : null),
             ]
         ]);
     }

     /*
      * Final confirmation for timesheet.
      */

      public function confirm()
      {
          $params = request()->validate([
              'person_id' => 'required|integer',
              'confirmed' => 'required|boolean',
          ]);

          $person = $this->findPerson($params['person_id']);
          $this->authorize('confirm', [Timesheet::class, $person->id]);

          $person->timesheet_confirmed = $params['confirmed'];

          // Only log the confirm/unconfirm if the flag changed.
          if ($person->isDirty('timesheet_confirmed')) {
              if ($person->timesheet_confirmed) {
                  $person->timesheet_confirmed_at = SqlHelper::now();
              } else {
                  $person->timesheet_confirmed_at = null;
              }

              $person->saveWithoutValidation();
              TimesheetLog::record('confirmed',
                    $person->id, $this->user->id, null,
                    ($person->timesheet_confirmed ? 'confirmed' : 'unconfirmed'));
          }

          return response()->json([
              'confirm_info' => [
                  'timesheet_confirmed' => (int) $person->timesheet_confirmed,
                  'timesheet_confirmed_at' => ($person->timesheet_confirmed ? (string) $person->timesheet_confirmed_at : null),
              ]
          ]);
      }

}

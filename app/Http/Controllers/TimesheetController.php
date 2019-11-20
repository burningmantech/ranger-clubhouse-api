<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;

use App\Helpers\SqlHelper;

use App\Models\PersonPosition;
use App\Models\Position;
use App\Models\PositionCredit;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Timesheet;
use App\Models\TimesheetLog;
use App\Models\TimesheetMissing;
use App\Models\Training;

use App\Lib\BulkSignInOut;

class TimesheetController extends ApiController
{
    /*
     * Retrieve a list of timesheets for a person and year.
     */
    public function index(Request $request)
    {
        $params = $request->validate([
            'year'      => 'sometimes|digits:4',
            'person_id' => 'sometimes|numeric',
            'on_duty'   => 'sometimes|boolean',
            'over_hours' => 'sometimes|integer',
        ]);

        $this->authorize('index', [ Timesheet::class, $params['person_id'] ?? null ]);

        $rows = Timesheet::findForQuery($params);

        if (!$rows->isEmpty()) {
            $year = $params['year'] ?? current_year();
            PositionCredit::warmYearCache($year, array_unique($rows->pluck('position_id')->toArray()));
        }

        return $this->success($rows, null, 'timesheet');
    }

    /**
     * Retrieve single timesheet
     */

    public function show(Timesheet $timesheet)
    {
        $this->authorize('index', [ Timesheet::class, $timesheet->person_id ]);
        return $this->success($timesheet);
    }


    /*
     * Retrieve a timesheet log for a person & year
     */
    public function showLog(Request $request)
    {
        $params = request()->validate([
            'year'      => 'required|digits:4',
            'person_id' => 'required|numeric'
        ]);

        $this->authorize('log', [ Timesheet::class, $params['person_id'] ]);

        list($logs, $other) = TimesheetLog::findForPersonYear($params['person_id'], $params['year']);

        $tsLogs = [];
        foreach ($logs as $ts) {
            $id = $ts->timesheet_id;
            if (!@$tsLogs[$id]) {
                $entry = $ts->timesheet;

                $tsLogs[$id] = [
                    'timesheet_id'  => $id,
                    'logs' => []
                ];

                if ($entry) {
                    $tsLogs[$id]['timesheet'] = [
                        'on_duty'        => (string) $entry->on_duty,
                        'off_duty'       => (string) $entry->off_duty,
                        'position_id'    => $entry->position_id,
                        'position_title' => $entry->position->title,
                    ];
                }
            }
            $tsLogs[$id]['logs'][] = [
                'timesheet_id'      => $ts->timesheet_id,
                'creator_person_id' => $ts->create_person_id,
                'creator_callsign'  => $ts->creator ? $ts->creator->callsign : "-",
                'created_at'        => (string) $ts->created_at,
                'action'            => $ts->action,
                'message'           => $ts->message,
            ];
        }

        $otherLogs = [];

        foreach ($other as $ts) {
            $otherLogs[] = [
                'creator_person_id' => $ts->create_person_id,
                'creator_callsign'  => $ts->creator ? $ts->creator->callsign : "-",
                'created_at'        => (string) $ts->created_at,
                'action'            => $ts->action,
                'message'           => $ts->message,
            ];
        }

        return response()->json([ 'logs' => array_values($tsLogs), 'other_logs' => $otherLogs]);
    }

    /*
     * Create a new timesheet
     */
    public function store(Request $request)
    {
        $timesheet = new Timesheet;

        $this->fromRest($timesheet);
        $this->authorize('store', $timesheet);

        if ($timesheet->save()) {
            $timesheet->loadRelationships();
            $this->log('timesheet-create', '', $timesheet, $timesheet->person_id);
            $person = $this->findPerson($timesheet->person_id);
            if ($person->timesheet_confirmed) {
                $person->timesheet_confirmed = false;
                $person->saveWithoutValidation();
                TimesheetLog::record('confirmed', $person->id, $this->user->id, null, 'unconfirmed - new entry created');
            }
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

        if ($timesheet->isDirty('on_duty')) {
            $updateInfo[] .= 'on duty old '.$timesheet->getOriginal('on_duty').' new '.$timesheet->on_duty;
        }

        if ($timesheet->isDirty('off_duty')) {
            $updateInfo[] .= 'off duty old '.$timesheet->getOriginal('off_duty').' new '.$timesheet->off_duty;
        }

        if ($timesheet->isDirty('position_id')) {
            $updateInfo[] = 'position old '.Position::retrieveTitle($timesheet->getOriginal('position_id'))
                            .' new '.Position::retrieveTitle($timesheet->position_id);
        }

        if ($timesheet->isDirty('review_status')) {
            $reviewInfo[] = 'status '.$timesheet->review_status;
        }

        // Update reviewer person if the review status or review notes changed
        if ($timesheet->isDirty('review_status') || $timesheet->isDirty('reviewer_notes')) {
            $timesheet->reviewer_person_id = $this->user->id;
        }

        if ($timesheet->isDirty('notes')) {
            $verifyInfo[] = 'note update';
            $timesheet->verified = false;
            if ($timesheet->review_status != 'pending') {
                $timesheet->review_status = 'pending';
                $verifyInfo[] = 'resubmit for review';
            }
        }

        if ($timesheet->isDirty('verified')) {
            if ($timesheet->verified) {
                $timesheet->setVerifiedAtToNow();
                $timesheet->verified_person_id = $this->user->id;
                $verifyInfo[] = 'verified';
            } else {
                $verifyInfo[] = 'marked incorrect';
                if ($person->timesheet_confirmed) {
                    $markedUnconfirmed = true;
                }
            }
        }

        $changes = $timesheet->getChangedValues();
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

        if ($markedUnconfirmed && $person->timesheet_confirmed) {
            $person->timesheet_confirmed = false;
            $person->saveWithoutValidation();
            TimesheetLog::record('confirmed', $person->id, $this->user->id, null, 'unconfirmed - entry marked incorrect');
        }

        // Load up position title, reviewer callsigns in case of change.
        $timesheet->loadRelationships();

        if (!empty($changes)) {
            $chanages['id'] = $timesheet->id;
            $this->log('timesheet-update', '', $changes, $timesheet->person_id);
        }

        return $this->success($timesheet);
    }

    /*
     * Delete a timesheet entry
     */
    public function destroy(Timesheet $timesheet)
    {
        $this->authorize('destroy', $timesheet);
        $timesheet->delete();

        $positionTitle = Position::retrieveTitle($timesheet->position_id);
        TimesheetLog::record(
            'delete',
            $timesheet->person_id,
            $this->user->id,
            $timesheet->id,
            "{$positionTitle} {$timesheet->on_duty} - {$timesheet->off_duty}"
        );

        $this->log('timesheet-delete', '', $timesheet, $timesheet->person_id);

        return $this->restDeleteSuccess();
    }

    /*
     * Start a shift for a person
     */

    public function signin(Request $request)
    {
        $this->authorize('signin', [ Timesheet::class ]);
        $canForceSignon = $this->userHasRole([ Role::ADMIN, Role::TIMESHEET_MANAGEMENT ]);

        $params = request()->validate([
            'person_id'    => 'required|integer',
            'position_id'  => 'required|integer|exists:position,id',
            'slot_id'      => 'sometimes|integer|exists:slot,id',
        ]);

        $personId = $params['person_id'];
        $positionId = $params['position_id'];

        // confirm person exists
        $person = $this->findPerson($personId);

        // Confirm the person is allowed to sign into the position
        if (!PersonPosition::havePosition($personId, $positionId)) {
            return response()->json([ 'status' => 'position-not-held' ]);
        }

        // they cannot be already on duty
        $onDuty = Timesheet::findPersonOnDuty($personId);
        if ($onDuty) {
            return response()->json([ 'status' => 'already-on-duty', 'timesheet' => $onDuty ]);
        }

        $signonForced = false;
        $required = null;
        $positionRequired = false;
        $unqualifiedReason = null;

        // Are they trained for this position?
        if (!Training::isPersonTrained($person, $positionId, current_year(), $requiredPositionId)) {
            $positionRequired = Position::retrieveTitle($requiredPositionId);
            if ($canForceSignon) {
                $signonForced = true;
            } else {
                return response()->json([
                    'status'         => 'not-trained',
                    'position_title' => $positionRequired,
                    'position_id'    => $requiredPositionId
                ]);
            }
        }

        // Sandman blocker - must be qualified
        if ($positionId == Position::SANDMAN && !Position::isSandmanQualified($person, $unqualifiedReason)) {
            if ($canForceSignon) {
                $signonForced = true;
            } else {
                return response()->json([
                    'status'         => 'not-qualified',
                    'unqualified_reason' => $unqualifiedReason,
                ]);
            }
        }

        $timesheet = new Timesheet($params);
        if (!$timesheet->slot_id) {
            // Try to associate a slot with the sign on
            $timesheet->slot_id = Schedule::findSlotSignUpByPositionTime($timesheet->person_id, $timesheet->position_id, $timesheet->on_duty);
        }

        $timesheet->setOnDutyToNow();

        if (!$timesheet->save()) {
            return $this->restError($timesheet);
        }

        $this->log('timesheet-create', 'sign in', $timesheet, $timesheet->person_id);

        $timesheet->loadRelationships();

        $message = '';
        $response = [
            'status'       => 'success',
            'timesheet_id' => $timesheet->id,
            'forced'       => $signonForced
        ];

        if ($signonForced) {
            $response['forced'] = true;
            if ($unqualifiedReason) {
                $message = "forced (unqualified {$unqualifiedReason}) ";
                $response['unqualified_reason'] = $unqualifiedReason;
            } else {
                $message = "forced (not trained {$positionRequired}) ";
                $response['required_training'] = $positionRequired;
            }
        }

        TimesheetLog::record(
                'signon',
                $person->id,
                $this->user->id,
                $timesheet->id,
                $message.
                $timesheet->position->title." ".(string) $timesheet->on_duty
            );

        return response()->json($response);
    }

    /*
     * End a shift
     */

    public function signoff(Timesheet $timesheet)
    {
        $this->authorize('signoff', $timesheet);

        if ($timesheet->off_duty) {
            return response()->json([ 'status' => 'already-signed-off', 'timesheet' => $timesheet ]);
        }

        $timesheet->setOffDutyToNow();
        $changes = $timesheet->getChangedValues();
        $timesheet->save();
        $timesheet->loadRelationships();
        $this->log('timesheet-update', 'signout', $timesheet, $timesheet->person_id);
        TimesheetLog::record(
            'signoff',
            $timesheet->person_id,
            $this->user->id,
            $timesheet->id,
            $timesheet->position->title." ".(string) $timesheet->off_duty
        );
        return response()->json([ 'status' => 'success', 'timesheet' => $timesheet ]);

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
                 'correction_year'     => setting('TimesheetCorrectionYear'),
                 'correction_enabled'  => setting('TimesheetCorrectionEnable'),
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
            TimesheetLog::record(
                'confirmed',
                $person->id,
                $this->user->id,
                null,
                ($person->timesheet_confirmed ? 'confirmed' : 'unconfirmed')
             );
        }

        return response()->json([
              'confirm_info' => [
                  'timesheet_confirmed' => (int) $person->timesheet_confirmed,
                  'timesheet_confirmed_at' => ($person->timesheet_confirmed ? (string) $person->timesheet_confirmed_at : null),
              ]
          ]);
    }

    /*
     * Timesheet Correction Requests Report
     */

    public function correctionRequests()
    {
        $params = request()->validate([
               'year'   => 'required|integer'
           ]);

        $year = $params['year'];

        $this->authorize('correctionRequests', [ Timesheet::class ]);

        return response()->json([
            'requests' => Timesheet::retrieveCombinedCorrectionRequestsForYear($year)
        ]);
    }
    /*
     * Timesheet Unconfirmed Report
     */

    public function unconfirmedPeople()
    {
        $params = request()->validate([
              'year' => 'required|integer'
          ]);

        $this->authorize('unconfirmedPeople', [ Timesheet::class ]);

        return response()->json([
              'unconfirmed_people' => Timesheet::retrieveUnconfirmedPeopleForYear($params['year'])
          ]);
    }

    /*
     * Timesheet Sanity Checker
     */

    public function sanityChecker()
    {
        $params = request()->validate([
              'year' => 'required|integer'
          ]);

        $this->authorize('sanityChecker', [ Timesheet::class ]);

        return response()->json(Timesheet::sanityChecker($params['year']));
    }

    /*
     * T-Shirts Earned Report
     */

    public function shirtsEarnedReport()
    {
        $this->authorize('shirtsEarnedReport', [ Timesheet::class ]);

        $params = request()->validate([
              'year' => 'required|integer'
          ]);

        $year = $params['year'];
        $thresholdLS = setting('ShirtLongSleeveHoursThreshold');
        $thresholdSS = setting('ShirtShortSleeveHoursThreshold');

        if (!$thresholdSS) {
            throw new \RuntimeException("ShirtShortSleeveHoursThreshold is not set");
        }

        if (!$thresholdLS) {
            throw new \RuntimeException("ShirtLongSleeveHoursThreshold is not set");
        }

        return response()->json([
              'people'  => Timesheet::retrieveEarnedShirts($year, $thresholdSS, $thresholdLS),
              'threshold_ss'    => $thresholdSS,
              'threshold_ls'    => $thresholdLS,
          ]);
    }

    /*
     * Freaking years report!
     */

    public function freakingYearsReport()
    {
        $this->authorize('freakingYearsReport', [ Timesheet::class ]);

        $params = request()->validate([
            'include_all' => 'sometimes|boolean'
        ]);

        $intendToWorkYear = current_year();

        return response()->json([
             'freaking' => Timesheet::retrieveFreakingYears($params['include_all'] ?? false, $intendToWorkYear),
             'signed_up_year' => $intendToWorkYear
          ]);
    }

    /*
     * Radio eligibility report
     */

    public function radioEligibilityReport()
    {
        $this->authorize('radioEligibilityReport', [ Timesheet::class ]);

        $params = request()->validate([
            'year' => 'required|integer'
        ]);

        return response()->json([ 'people' => Timesheet::retrieveRadioEligilibity($params['year']) ]);
    }

    /*
     * Bulk Sign In and/or Out action
     */

    public function bulkSignInOut()
    {
        $this->authorize('bulkSignInOut', [ Timesheet::class ]);

        $params = request()->validate([
            'lines'  => 'string|required_without:csv',
            'csv'    => 'file|required_without:lines',
            'commit' => 'sometimes|boolean'
        ]);

        $commit = $params['commit'] ?? false;

        if (isset($params['lines'])) {
            $people = $params['lines'];
        } else {
            $people = $params['csv']->get();
        }

        list($entries, $haveError) = BulkSignInOut::parse($people);

        if ($haveError) {
            return response()->json([ 'status' => 'error', 'entries' => $entries, 'commit' => false ]);
        }

        if (!$commit) {
            return response()->json([ 'status' => 'success', 'entries' => $entries, 'commit' => false ]);
        }

        $userId = $this->user->id;

        $haveError = false;
        foreach ($entries as $entry) {
            $personId      = $entry->person_id;
            $positionId    = $entry->position_id;
            $signin        = $entry->signin;
            $signout       = $entry->signout;
            $positionTitle = $entry->position;
            $action        = $entry->action;

            switch ($action) {
            case 'inout':
                // Sign in & out - create timesheet
                $timesheet = new Timesheet([
                    'person_id'   => $personId,
                    'position_id' => $positionId,
                    'on_duty'     => $signin,
                    'off_duty'    => $signout,
                ]);
                $event = 'created';
                $message = "bulk upload $positionTitle $signin - $signout";
                break;

            case 'in':
                // Sign in - create timesheet
                if (empty($signin)) {
                    $signin = SqlHelper::now();
                }

                $timesheet = new Timesheet([
                    'person_id'   => $personId,
                    'position_id' => $positionId,
                    'on_duty'     => $signin,
                ]);
                $entry->signin = $signin;

                $event = 'signon';
                $message = "bulk upload $positionTitle {$signin}";
                break;

            case 'out':
                $timesheetId = $entry->timesheet_id;
                $timesheet = Timesheet::find($timesheetId);
                if (!$timesheet) {
                    // Impossible condition?
                    $entry->errors = [ "cannot find timesheet id=[$timesheetId]? "];
                    $haveError = true;
                    continue 2;
                }

                if (empty($signout)) {
                    $signout = SqlHelper::now();
                }
                $timesheet->off_duty = $signout;
                $event = 'signoff';
                $message = "bulk upload $positionTitle $signout";
                break;
            }

            if (!$timesheet->slot_id) {
                // Try to associate a sign up with the entry
                $timesheet->slot_id = Schedule::findSlotSignUpByPositionTime($timesheet->person_id, $timesheet->position_id, $timesheet->on_duty);
            }

            $isExisting = $timesheet->id ? true : false;
            if ($isExisting) {
                $changes = $timesheet->getChangedValues();
            }

            if ($timesheet->save()) {
                if ($isExisting) {
                    $changes['id'] = $timesheet->id;
                    $this->log('timesheet-update', 'bulk sign in/out', $changes, $timesheet->person_id);
                } else {
                    $this->log('timesheet-create', 'bulk sign in/out', $timesheet, $timesheet->person_id);
                }
                TimesheetLog::record($event, $personId, $userId, $timesheet->id, $message);
            } else {
                $entry->errors = [ "timesheet entry save failure ".json_encode($timesheet->getErrors()) ];
                $haveError = true;
            }
        }
        return response()->json([ 'status' => ($haveError ? 'error' : 'success'), 'entries' => $entries, 'commit' => true ]);
    }

    /*
     * Special Teams reporting
     */

    public function specialTeamsReport()
    {
        $this->authorize('specialTeamsReport', [ Timesheet::class ]);

        $params = request()->validate([
             'position_ids'   => 'required|array',
             'position_ids.*' => 'integer|exists:position,id',
             'start_year'     => 'required|integer|lte:end_year',
             'end_year'       => 'required|integer',
             'include_inactive' => 'sometimes|boolean'
         ]);

        return response()->json([
             'people' => Timesheet::retrieveSpecialTeamsWork(
                            $params['position_ids'],
                 $params['start_year'],
                            $params['end_year'],
                 ($params['include_inactive'] ?? false),
                            $this->userCanViewEmail()
             )
        ]);
    }

    /*
     * Hours/Credits report
     */

    public function hoursCreditsReport()
    {
        $this->authorize('hoursCreditsReport', [ Timesheet::class ]);

        $year = $this->getYear();

        return response()->json(Timesheet::retrieveHoursCredits($year));
    }

    /*
     * Thank You cards
     */

    public function thankYou()
    {
        $this->authorize('thankYou', [ Timesheet::class ]);

        $params = request()->validate([
            'password'  => 'required|string',
            'year' => 'required|integer',
        ]);

        if (hash('sha256', $params['password']) != setting('ThankYouCardsHash')) {
            $this->notPermitted('Invalid password');
        }

        return response()->json([ 'people' => Timesheet::retrievePeopleToThank($params['year']) ]);
    }

    /*
     * Timesheet by Callsign report
     */

    public function timesheetByCallsign()
    {
        $this->authorize('timesheetByCallsign', [ Timesheet::class ]);

        $year = $this->getYear();

        return response()->json([ 'people' => Timesheet::retrieveAllForYearByCallsign($year) ]);
    }

    /*
     * Timesheet Totals Report
     */

    public function timesheetTotals()
    {
        $this->authorize('timesheetTotals', [ Timesheet::class ]);
        $year = $this->getYear();

        return response()->json([ 'people' => TImesheet::retrieveTimesheetTotals($year) ]);
    }

    /*
     * Timesheet By Position
     */

     public function timesheetByPosition()
     {
         $this->authorize('timesheetByPosition', [ Timesheet::class ]);
         $year = $this->getYear();

         return response()->json([ 'positions' => TImesheet::retrieveByPosition($year, $this->userCanViewEmail() ) ]);

     }
}

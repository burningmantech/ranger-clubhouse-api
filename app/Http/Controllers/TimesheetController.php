<?php

namespace App\Http\Controllers;

use App\Exceptions\UnacceptableConditionException;
use App\Lib\BulkSignInOut;
use App\Lib\Reports\CombinedTimesheetCorrectionRequestsReport;
use App\Lib\Reports\EarlyLateCheckInReport;
use App\Lib\Reports\EventStats;
use App\Lib\Reports\ForcedSigninsReport;
use App\Lib\Reports\FreakingYearsReport;
use App\Lib\Reports\HoursCreditsReport;
use App\Lib\Reports\NoShowReport;
use App\Lib\Reports\OnDutyReport;
use App\Lib\Reports\OnDutyShiftLeadReport;
use App\Lib\Reports\PayrollReport;
use App\Lib\Reports\PeopleWithUnconfirmedTimesheetsReport;
use App\Lib\Reports\RadioEligibilityReport;
use App\Lib\Reports\RangerRetentionReport;
use App\Lib\Reports\SpecialTeamsWorkReport;
use App\Lib\Reports\ThankYouCardsReport;
use App\Lib\Reports\TimesheetByCallsignReport;
use App\Lib\Reports\TimesheetByPositionReport;
use App\Lib\Reports\TimesheetCorrectionsStatisticsReport;
use App\Lib\Reports\TimesheetSanityCheckReport;
use App\Lib\Reports\TimesheetTotalsReport;
use App\Lib\Reports\TopHourEarnersReport;
use App\Lib\ShiftDropReport;
use App\Lib\SignInBlocker;
use App\Lib\TimesheetManagement;
use App\Lib\TimesheetSlotAssocRepair;
use App\Mail\AutomaticActiveConversionMail;
use App\Models\ActionLog;
use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\PersonPosition;
use App\Models\Position;
use App\Models\PositionCredit;
use App\Models\Role;
use App\Models\Timesheet;
use App\Models\TimesheetLog;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Psr\SimpleCache\InvalidArgumentException;

class TimesheetController extends ApiController
{
    /**
     * Retrieve a list of timesheets for a person and year.
     *
     * @return JsonResponse
     * @throws AuthorizationException|InvalidArgumentException
     */
    public function index(): JsonResponse
    {
        $params = request()->validate([
            'year' => 'sometimes|digits:4',
            'person_id' => 'sometimes|numeric',
            'is_on_duty' => 'sometimes|boolean',
            'duty_date' => 'sometimes|date',
            'over_hours' => 'sometimes|integer',
            'on_duty_start' => 'sometimes|date',
            'on_duty_end' => 'sometimes|date',
            'position_id' => 'sometimes|integer',
            'position_ids' => 'sometimes|array',
            'position_ids.*' => 'integer|exists:position,id',
            'include_photo' => 'sometimes|boolean',
            'include_admin_notes' => 'sometimes|boolean',
            'check_times' => 'sometimes|boolean',
        ]);

        $this->authorize('index', [Timesheet::class, $params['person_id'] ?? null]);

        if ($params['include_admin_notes'] ?? false) {
            Gate::authorize('isTimesheetManager');
        }

        $rows = Timesheet::findForQuery($params);

        // Find all the years and positions to warm the position credit cache
        $years = [];
        foreach ($rows as $row) {
            $year = $row->on_duty->year;
            $positionId = $row->position_id;
            if (!isset($years[$year])) {
                $years[$year] = [$positionId];
            } else if (!in_array($positionId, $years[$year])) {
                $years[$year][] = $positionId;
            }
        }

        PositionCredit::warmBulkYearCache($years);

        return $this->success($rows, null, 'timesheet');
    }

    /**
     * Retrieve single timesheet
     *
     * @param Timesheet $timesheet
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(Timesheet $timesheet): JsonResponse
    {
        $this->authorize('index', [Timesheet::class, $timesheet->person_id]);
        $timesheet->loadRelationships(Gate::allows('isTimesheetManager'));
        $timesheet->checkTimes();
        return $this->success($timesheet);
    }

    /**
     * Retrieve a timesheet log for a person & year
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function showLog(): JsonResponse
    {
        $params = request()->validate([
            'year' => 'required|digits:4',
            'person_id' => 'required|numeric'
        ]);

        $personId = $params['person_id'];
        $this->authorize('log', [Timesheet::class, $personId]);

        list($logs, $other) = TimesheetLog::findForPersonYear($personId, $params['year']);

        $tsLogs = [];
        foreach ($logs as $ts) {
            $id = $ts->timesheet_id;
            if (!isset($tsLogs[$id])) {
                $entry = $ts->timesheet;

                $tsLogs[$id] = [
                    'timesheet_id' => $id,
                    'logs' => []
                ];

                if ($entry) {
                    $tsLogs[$id]['timesheet'] = [
                        'on_duty' => (string)$entry->on_duty,
                        'off_duty' => (string)$entry->off_duty,
                        'position_id' => $entry->position_id,
                        'position_title' => $entry->position->title,
                    ];
                }
            }

            if ($ts->action == TimesheetLog::DELETE) {
                // Fake up a timesheet record.
                $data = $ts->decodeData();
                $tsLogs[$id]['timesheet'] = [
                    'on_duty' => (string)($data->on_duty ?? 'unknown on duty'),
                    'off_duty' => (string)($data->off_duty ?? ''),  // some shifts started, not ended, and then deleted.
                    'position_id' => $data->position_id ?? '',
                    'position_title' => isset($data->position_id) ? Position::retrieveTitle($data->position_id) : 'position not set',
                ];
                $tsLogs[$id]['deleted'] = true;
            }
            $tsLogs[$id]['logs'][] = [
                'timesheet_id' => $ts->timesheet_id,
                'creator_person_id' => $ts->create_person_id,
                'creator_callsign' => $ts->creator ? $ts->creator->callsign : "-",
                'created_at' => (string)$ts->created_at,
                'action' => $ts->action,
                'data' => $ts->decodeData(),
            ];
        }

        usort($tsLogs, function ($a, $b) {
            $aDate = $a['timesheet']['on_duty'] ?? '2099-01-01';
            $bDate = $b['timesheet']['on_duty'] ?? '2099-01-01';
            return strcmp($aDate, $bDate);
        });

        $otherLogs = [];

        foreach ($other as $ts) {
            $otherLogs[] = [
                'creator_person_id' => $ts->create_person_id,
                'creator_callsign' => $ts->creator ? $ts->creator->callsign : "-",
                'created_at' => (string)$ts->created_at,
                'action' => $ts->action,
                'data' => $ts->decodeData(),
            ];
        }

        return response()->json(['logs' => array_values($tsLogs), 'other_logs' => $otherLogs]);
    }

    /**
     * Create a new timesheet
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function store(): JsonResponse
    {
        $timesheet = new Timesheet;

        $this->fromRest($timesheet);
        $this->authorize('store', $timesheet);

        if ($timesheet->save()) {
            $timesheet->loadRelationships(Gate::allows('isTimesheetManager'));
            $timesheet->checkTimes();
            TimesheetManagement::unconfirmTimesheet($timesheet, 'new entry');
            return $this->success($timesheet);
        }

        return $this->restError($timesheet);
    }

    /**
     * Update an existing timesheet
     *
     * @param Timesheet $timesheet
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function update(Timesheet $timesheet): JsonResponse
    {
        $this->authorize('update', $timesheet);

        $this->fromRestFiltered($timesheet);

        $markedUnconfirmed = false;
        $year = $timesheet->on_duty->year;
        $userId = $this->user->id;

        if (!Gate::allows('isTimesheetManager')
            && $timesheet->isDirty('review_status')) {
            $status = $timesheet->review_status;
            if ($status != Timesheet::STATUS_UNVERIFIED
                && $status != Timesheet::STATUS_VERIFIED
                && $status != Timesheet::STATUS_PENDING) {
                $timesheet->addError('review_status', 'Unauthorized status.');
                return $this->restError($timesheet);
            }
        }

        // Update reviewer person if the review status or review notes changed
        if ($timesheet->isDirty('review_status') || !empty($timesheet->additionalWranglerNotes)) {
            $timesheet->reviewer_person_id = $userId;
        }

        if (
            !empty($timesheet->additionalNotes)
            || $timesheet->isDirty('desired_position_id')
            || $timesheet->isDirty('desired_on_duty')
            || $timesheet->isDirty('desired_off_duty')
        ) {
            $timesheet->review_status = Timesheet::STATUS_PENDING;
        }

        if ($timesheet->isDirty('review_status')) {
            if ($timesheet->review_status == Timesheet::STATUS_VERIFIED) {
                $timesheet->verified_at = now();
                $timesheet->verified_person_id = $userId;
            } else {
                $markedUnconfirmed = true;
            }
        }

        $auditColumns = [];
        foreach (['on_duty', 'off_duty', 'position_id', 'review_status'] as $column) {
            if ($timesheet->isDirty($column)) {
                $old = $timesheet->getOriginal($column);
                $new = $timesheet->getAttribute($column);
                switch ($column) {
                    case 'on_duty':
                    case 'off_duty':
                        $old = (string)$old;
                        $new = (string)$new;
                        break;
                }
                $auditColumns[$column] = [$old, $new];
            }
        }

        if (!$timesheet->save()) {
            return $this->restError($timesheet);
        }

        if (!empty($auditColumns)) {
            $didVerify = false;
            $didUnverify = false;

            if (isset($auditColumns['review_status'])) {
                $didVerify = $timesheet->review_status == Timesheet::STATUS_VERIFIED;
                $didUnverify = $timesheet->review_status == Timesheet::STATUS_UNVERIFIED;
            }

            if (count($auditColumns) != 1 || (!$didVerify && !$didUnverify)) {
                // Don't record only the verification..
                $timesheet->log(TimesheetLog::UPDATE, $auditColumns);
            }

            if ($didVerify) {
                $timesheet->log(TimesheetLog::VERIFY);
            } else if ($didUnverify) {
                $timesheet->log(TimesheetLog::UNVERIFIED);
            }

            if (isset($auditColumns['on_duty'])) {
                // Update all the other logs in case the year changed.
                TimesheetLog::updateYear($timesheet->id, $year);
            }
        }

        if ($markedUnconfirmed) {
            TimesheetManagement::unconfirmTimesheet($timesheet, "marked {$timesheet->review_status}");
        }

        // Reload position title, reviewer callsigns in case of change.
        $timesheet->loadRelationships(Gate::allows('isTimesheetManager'));
        $timesheet->checkTimes();

        return $this->success($timesheet);
    }

    /**
     * Delete a timesheet entry
     *
     * @param Timesheet $timesheet
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(Timesheet $timesheet): JsonResponse
    {
        $this->authorize('destroy', $timesheet);
        $timesheet->delete();

        return $this->restDeleteSuccess();
    }

    /**
     * Start a shift for a person
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function signin(): JsonResponse
    {

        $params = request()->validate([
            'person_id' => 'required|integer|exists:person,id',
            'position_id' => 'required|integer|exists:position,id',
            'slot_id' => 'sometimes|integer|exists:slot,id',
            'force_sign_in' => 'sometimes|boolean',
            'signin_force_reason' => 'sometimes|string',
            'start_time' => 'sometimes|date_format:H:i',
        ]);

        $personId = $params['person_id'];
        $positionId = $params['position_id'];
        $this->authorize('signin', [Timesheet::class, $personId]);

        // they cannot be on duty
        $onDuty = Timesheet::findPersonOnDuty($personId);
        if ($onDuty) {
            return response()->json([
                'status' => 'already-on-duty',
                'timesheet' => $onDuty
            ]);
        }

        $person = Person::find($personId);
        $position = Position::find($positionId);

        // Confirm the person is allowed to sign in to the position
        if (!PersonPosition::havePosition($personId, $positionId)) {
            // Punt immediately.
            return response()->json(['status' => 'position-not-held']);
        }

        if ($position->not_timesheet_eligible) {
            // Punt immediately
            return response()->json(['status' => 'position-not-eligible']);
        }

        $now = now();

        $startTime = $params['start_time'] ?? null;
        if ($startTime) {
            list($hour, $minute) = explode(':', $startTime);
            $onDuty = $now->clone()->setHour((int)$hour)->setMinute((int)$minute);
            if ($onDuty->gt($now)) {
                throw ValidationException::withMessages([
                    'start_time' => ['Start time is in the future']
                ]);
            }

            $log['set_time'] = true;
        } else {
            $onDuty = $now;
        }

        $blockers = SignInBlocker::check($person, $position, true, $onDuty);
        $wasForced = false;

        if (!empty($blockers)) {
            if (!($params['force_sign_in'] ?? false)) {
                return response()->json(['status' => 'blocked', 'blockers' => $blockers, 'start_time' => $onDuty]);
            }

            Gate::allowIf(fn($user) => $user->hasRole([Role::ADMIN, Role::CAN_FORCE_SHIFT]));

            if (empty($params['signin_force_reason'])) {
                return response()->json(['status' => 'missing-force-reason']);
            }
            $wasForced = true;
        }


        $timesheet = new Timesheet($params);
        $timesheet->on_duty = $onDuty;
        $timesheet->auditReason = 'sign in';
        $timesheet->was_signin_forced = $wasForced;
        if (!$timesheet->save()) {
            return $this->restError($timesheet);
        }

        TimesheetManagement::unconfirmTimesheet($timesheet, 'new entry - signed in');
        $timesheet->loadRelationships(Gate::allows('isTimesheetManager'));

        $log = [
            'position_id' => $timesheet->position_id,
            'on_duty' => (string)$timesheet->on_duty
        ];

        if ($wasForced) {
            $log['signin_force_reason'] = $timesheet->signin_force_reason;
        }

        return TimesheetManagement::reportSignIn(TimesheetLog::SIGNON, $timesheet, $wasForced, $blockers, $log);
    }

    /**
     * Update on duty Timesheet to the new position.
     *
     * @param Timesheet $timesheet
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException|UnacceptableConditionException
     */

    public function updatePosition(Timesheet $timesheet): JsonResponse
    {
        $this->authorize('updatePosition', $timesheet);
        $params = request()->validate([
            'position_id' => 'required|integer|exists:position,id',
            'force_sign_in' => 'sometimes|boolean',
            'signin_force_reason' => 'sometimes|string',
        ]);

        if ($timesheet->off_duty) {
            throw new UnacceptableConditionException('Timesheet entry is not off duty.');
        }

        $positionId = $params['position_id'];
        $person = $timesheet->person;
        $position = Position::find($positionId);

        // Confirm the person is allowed to sign in to the position
        if (!PersonPosition::havePosition($person->id, $positionId)) {
            // Punt immediately.
            return response()->json(['status' => 'position-not-held']);
        }

        if ($position->not_timesheet_eligible) {
            // Punt immediately
            return response()->json(['status' => 'position-not-eligible']);
        }

        $blockers = SignInBlocker::check($person, $position, true);
        $wasForced = false;

        if (!empty($blockers)) {
            if (!($params['force_sign_in'] ?? false)) {
                return response()->json(['status' => 'blocked', 'blockers' => $blockers]);
            }

            Gate::allowIf(fn($user) => $user->hasRole([Role::ADMIN, Role::CAN_FORCE_SHIFT]));

            if (empty($params['signin_force_reason'])) {
                return response()->json(['status' => 'missing-force-reason']);
            }
            $wasForced = true;
        }

        $log = [
            'position_id' => [$timesheet->position_id, $positionId],
        ];

        $timesheet->position_id = $positionId;
        if ($wasForced) {
            $reason = $params['signin_force_reason'];
            $timesheet->was_signin_forced = true;
            $timesheet->signin_force_reason = $reason;
            $log['signin_force_reason'] = $reason;
        }
        $timesheet->auditReason = 'position update while on duty';
        $timesheet->saveOrThrow();

        return TimesheetManagement::reportSignIn(TimesheetLog::UPDATE, $timesheet, $wasForced, $blockers, $log);
    }

    /**
     * End a shift
     *
     * @param Timesheet $timesheet
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function signoff(Timesheet $timesheet): JsonResponse
    {
        $this->authorize('signoff', $timesheet);

        if ($timesheet->off_duty) {
            return response()->json(['status' => 'already-signed-off', 'timesheet' => $timesheet]);
        }

        $timesheet->off_duty = now();
        $timesheet->auditReason = 'signout';
        $timesheet->saveOrThrow();
        $timesheet->log(TimesheetLog::SIGNOFF, [
            'position_id' => $timesheet->position_id,
            'off_duty' => (string)$timesheet->off_duty,
        ]);
        $timesheet->loadRelationships(Gate::allows('isTimesheetManager'));

        TimesheetManagement::unconfirmTimesheet($timesheet, 'signoff');

        $response = ['status' => 'success', 'timesheet' => $timesheet];

        $timesheet->load('position:id,title,type');
        $person = $timesheet->person;
        $status = $person->status;
        if ($status == Person::INACTIVE
            && $timesheet->position->type != Position::TYPE_TRAINING
            && $timesheet->position_id != Position::CHEETAH_CUB) {
            $person->status = Person::ACTIVE;
            $person->auditReason = 'automatic status conversion';
            $person->saveWithoutValidation();
            $response['now_active_status'] = true;
            mail_send(new AutomaticActiveConversionMail($person, $status, $timesheet->position->title, $this->user->callsign));
        }

        return response()->json($response);
    }

    /**
     * Delete an accidental shift.
     *
     * @param Timesheet $timesheet
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws UnacceptableConditionException
     */

    public function deleteMistake(Timesheet $timesheet): JsonResponse
    {
        $this->authorize('deleteMistake', $timesheet);

        if ($timesheet->off_duty) {
            throw new UnacceptableConditionException('Delete mistake request can only happen on still on duty entries.');
        }

        $timesheet->auditReason = 'accidental creation';
        $timesheet->delete();

        $timesheet->log(TimesheetLog::DELETE_MISTAKE, ['position_id' => $timesheet->position_id,]);

        return response()->json(['status' => 'success', 'timesheet' => $timesheet]);
    }

    /**
     * Restart a shift - use for accidental sign out.
     *
     * @param Timesheet $timesheet
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function resignin(Timesheet $timesheet): JsonResponse
    {
        $this->authorize('resignin', $timesheet);

        $personId = request()->input('person_id');

        if ($personId != $timesheet->person_id) {
            return response()->json(['status' => 'person-mismatch']);
        }

        // Ensure person is not already on duty
        $onDuty = Timesheet::findPersonOnDuty($personId);
        if ($onDuty) {
            return response()->json(['status' => 'already-on-duty', 'timesheet' => $timesheet]);
        }

        $offDuty = $timesheet->off_duty;
        $timesheet->off_duty = null;
        $timesheet->auditReason = 're-signin';
        $timesheet->save();
        $timesheet->log(TimesheetLog::UPDATE, ['off_duty' => [(string)$offDuty, 're-signin']]);
        TimesheetManagement::unconfirmTimesheet($timesheet, 're-signin');
        return response()->json(['status' => 'success', 'timesheet' => $timesheet]);
    }

    /**
     * Return information on timesheet corrections AND the current timesheet
     * confirmation status for a person.
     *
     * @return JsonResponse
     */

    public function info(): JsonResponse
    {
        $params = request()->validate([
            'person_id' => 'required|integer'
        ]);

        $year = current_year();
        $person = $this->findPerson($params['person_id']);
        $event = PersonEvent::firstOrNewForPersonYear($person->id, $year);

        return response()->json([
            'info' => [
                'correction_year' => $year,
                'correction_enabled' => setting('TimesheetCorrectionEnable'),
                'timesheet_confirmed' => (int)$event->timesheet_confirmed,
                'timesheet_confirmed_at' => ($event->timesheet_confirmed ? (string)$event->timesheet_confirmed_at : null),
            ]
        ]);
    }

    /**
     * Final confirmation for timesheet.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function confirm(): JsonResponse
    {
        $params = request()->validate([
            'person_id' => 'required|integer',
            'confirmed' => 'required|boolean',
        ]);

        $person = $this->findPerson($params['person_id']);
        $this->authorize('confirm', [Timesheet::class, $person->id]);

        $event = PersonEvent::firstOrNewForPersonYear($person->id, current_year());
        $event->auditReason = 'timesheet confirm';
        $event->timesheet_confirmed = $params['confirmed'];

        // Only log the confirm/unconfirm if the flag changed.
        if ($event->isDirty('timesheet_confirmed')) {
            $event->timesheet_confirmed_at = $event->timesheet_confirmed ? now() : null;
            $event->saveWithoutValidation();
            TimesheetLog::record(
                $event->timesheet_confirmed ? TimesheetLog::CONFIRMED : TimesheetLog::UNCONFIRMED,
                $person->id,
                $this->user->id,
                null,
                null
            );
        }

        return response()->json([
            'confirm_info' => [
                'timesheet_confirmed' => (int)$event->timesheet_confirmed,
                'timesheet_confirmed_at' => ($event->timesheet_confirmed ? (string)$event->timesheet_confirmed_at : null),
            ]
        ]);
    }

    /**
     * Timesheet Correction Requests Report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function correctionRequests(): JsonResponse
    {
        $this->authorize('correctionRequests', [Timesheet::class]);
        $year = $this->getYear();

        return response()->json([
            'requests' => CombinedTimesheetCorrectionRequestsReport::execute($year)
        ]);
    }

    /**
     * Timesheet Unconfirmed Report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function unconfirmedPeople(): JsonResponse
    {
        $this->authorize('unconfirmedPeople', [Timesheet::class]);
        $year = $this->getYear();
        return response()->json([
            'unconfirmed_people' => PeopleWithUnconfirmedTimesheetsReport::execute($year)
        ]);
    }

    /**
     * Timesheet Sanity Checker
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function sanityChecker(): JsonResponse
    {
        $this->authorize('sanityChecker', [Timesheet::class]);
        $year = $this->getYear();
        return response()->json(TimesheetSanityCheckReport::execute($year));
    }


    /**
     * Freaking years report!
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function freakingYearsReport(): JsonResponse
    {
        $this->authorize('freakingYearsReport', [Timesheet::class]);

        $params = request()->validate([
            'include_all' => 'sometimes|boolean'
        ]);

        $intendToWorkYear = current_year();
        return response()->json([
            'freaking' => FreakingYearsReport::execute($params['include_all'] ?? false, $intendToWorkYear),
            'signed_up_year' => $intendToWorkYear
        ]);
    }

    /**
     * Radio eligibility report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function radioEligibilityReport(): JsonResponse
    {
        $this->authorize('radioEligibilityReport', [Timesheet::class]);
        $year = $this->getYear();

        return response()->json(RadioEligibilityReport::execute($year));
    }

    /**
     * Bulk Sign In and/or Out action
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function bulkSignInOut(): JsonResponse
    {
        $this->authorize('bulkSignInOut', [Timesheet::class]);

        $params = request()->validate([
            'lines' => 'string|required_without:csv',
            'csv' => 'file|required_without:lines',
            'commit' => 'sometimes|boolean'
        ]);

        $commit = $params['commit'] ?? false;

        $people = $params['lines'] ?? $params['csv']->get();

        list($entries, $haveError) = BulkSignInOut::parse($people);

        if ($haveError || !$commit) {
            return response()->json(['status' => ($haveError ? 'error' : 'success'), 'entries' => $entries, 'commit' => false]);
        }

        $haveError = BulkSignInOut::process($entries, $this->user->id);
        ActionLog::record($this->user, 'bulk-sign-in-out', '', ['lines' => $people, 'processed' => $entries]);

        return response()->json(['status' => ($haveError ? 'error' : 'success'), 'entries' => $entries, 'commit' => true]);
    }

    /**
     * Special Teams Work Report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function specialTeamsReport(): JsonResponse
    {
        $this->authorize('specialTeamsReport', [Timesheet::class]);

        $params = request()->validate([
            'position_ids' => 'required|array',
            'position_ids.*' => 'integer|exists:position,id',
            'start_year' => 'required|integer|lte:end_year',
            'end_year' => 'required|integer',
            'include_inactive' => 'sometimes|boolean'
        ]);

        return response()->json([
            'people' => SpecialTeamsWorkReport::execute(
                $params['position_ids'],
                $params['start_year'],
                $params['end_year'],
                ($params['include_inactive'] ?? false),
                $this->userCanViewEmail()
            )
        ]);
    }

    /**
     * Hours/Credits report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function hoursCreditsReport(): JsonResponse
    {
        $this->authorize('hoursCreditsReport', [Timesheet::class]);
        $year = $this->getYear();
        return response()->json(HoursCreditsReport::execute($year));
    }

    /**
     * Thank You cards
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function thankYou(): JsonResponse
    {
        $this->authorize('thankYou', [Timesheet::class]);

        $params = request()->validate([
            'password' => 'required|string',
            'year' => 'required|integer',
        ]);

        if (hash('sha256', $params['password']) != setting('ThankYouCardsHash', true)) {
            $this->notPermitted('Invalid password');
        }

        return response()->json(['people' => ThankYouCardsReport::execute($params['year'])]);
    }

    /**
     * Timesheet by Callsign report
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function timesheetByCallsign(): JsonResponse
    {
        $this->authorize('timesheetByCallsign', [Timesheet::class]);
        $year = $this->getYear();
        return response()->json(TimesheetByCallsignReport::execute($year));
    }

    /**
     * Timesheet Totals Report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function timesheetTotals(): JsonResponse
    {
        $this->authorize('timesheetTotals', [Timesheet::class]);
        $year = $this->getYear();
        return response()->json(['people' => TimesheetTotalsReport::execute($year)]);
    }

    /**
     * Timesheet By Position
     *
     * @return JsonResponse
     * @throws AuthorizationException|InvalidArgumentException
     */

    public function timesheetByPosition(): JsonResponse
    {
        $this->authorize('timesheetByPosition', [Timesheet::class]);
        $year = $this->getYear();
        return response()->json(TimesheetByPositionReport::execute($year, $this->userCanViewEmail()));
    }

    /**
     * Who is on duty?
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function onDutyReport(): JsonResponse
    {
        $this->authorize('onDutyReport', [Timesheet::class]);
        $params = request()->validate([
            'duty_date' => 'sometimes|date',
            'has_excessive_duration' => 'sometimes|boolean',
        ]);

        return response()->json(OnDutyReport::execute($params['duty_date'] ?? null, $params['has_excessive_duration'] ?? false));
    }

    /**
     * The On Duty Shift Lead Report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws Exception
     */

    public function onDutyShiftLeadReport(): JsonResponse
    {
        $this->authorize('onDutyShiftLeadReport', [Timesheet::class]);

        return response()->json(OnDutyShiftLeadReport::execute());
    }


    /**
     * Run the Ranger Retention Report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function retentionReport(): JsonResponse
    {
        $this->authorize('retentionReport', Timesheet::class);

        return response()->json(RangerRetentionReport::execute());
    }

    /**
     * The Top Hour Earners report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function topHourEarnersReport(): JsonResponse
    {
        $this->authorize('topHourEarnersReport', Timesheet::class);
        $params = request()->validate([
            'start_year' => 'integer|gte:2010',
            'end_year' => 'integer|gte:2010',
            'limit' => 'integer|gt:0|lt:300'
        ]);

        return response()->json(['top_earners' => TopHourEarnersReport::execute($params['start_year'], $params['end_year'], $params['limit'])]);
    }

    /**
     * Scan the timesheet entries for a given year and repair any broken the slot (sign-up) associations.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function repairSlotAssociations(): JsonResponse
    {
        $this->authorize('repairSlotAssociations', Timesheet::class);
        $year = $this->getYear();

        return response()->json(['entries' => TimesheetSlotAssocRepair::execute($year)]);
    }

    /**
     * Event Statistics
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function eventStatsReport(): JsonResponse
    {
        $this->authorize('eventStatsReport', Timesheet::class);
        return response()->json(['stats' => EventStats::execute($this->getYear())]);
    }


    /**
     * Payroll report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function payrollReport(): JsonResponse
    {
        Gate::allowIf(fn($user) => $user->hasRole(Role::PAYROLL));

        $params = request()->validate([
            'start_time' => 'required|date|before:end_time',
            'end_time' => 'required|date|after:start_time',
            'break_duration' => 'required|integer',
            'break_after' => 'sometimes|integer',
            'position_ids' => 'required|array',
            'position_ids.*' => 'integer|exists:position,id'
        ]);

        return response()->json(
            PayrollReport::execute(
                $params['start_time'],
                $params['end_time'],
                $params['break_after'] ?? 0,
                $params['break_duration'],
                $params['position_ids'])
        );
    }

    /**
     * Shift Drop Report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function shiftDropReport(): JsonResponse
    {
        $this->authorize('shiftDropReport', Timesheet::class);

        $params = request()->validate([
            'position_ids' => 'required|array',
            'position_ids.*' => 'integer|exists:position,id',
            'year' => 'required|integer',
            'hours' => 'required|numeric|between:1,48',
        ]);

        return response()->json(ShiftDropReport::execute($params['position_ids'], $params['year'], $params['hours']));
    }

    /**
     * No Shows Report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function noShowsReport(): JsonResponse
    {
        $this->authorize('noShowsReport', Timesheet::class);

        $params = request()->validate([
            'position_ids' => 'required|array',
            'position_ids.*' => 'integer|exists:position,id',
            'year' => 'required|integer',
        ]);

        return response()->json([
            'positions' => NoShowReport::execute($params['position_ids'], $params['year'])
        ]);
    }


    /**
     * Forced Sign-In Report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function forcedSigninsReport(): JsonResponse
    {
        $this->authorize('forcedSigninsReport', Timesheet::class);
        $year = $this->getYear();

        return response()->json(ForcedSigninsReport::execute($year));
    }

    /**
     * Timesheet Correction Request Statistics
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function correctionStatistics(): JsonResponse
    {
        $this->authorize('correctionStatistics', Timesheet::class);
        $year = $this->getYear();

        return response()->json(['statistics' => TimesheetCorrectionsStatisticsReport::execute($year)]);
    }

    /**
     * Check if the given on and off duty times overlap with any existing timesheets.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function checkForOverlaps(): JsonResponse
    {
        $params = request()->validate([
            'person_id' => 'required|integer|exists:person,id',
            'timesheet_id' => 'sometimes|exists:timesheet,id',
            'on_duty' => 'required|date',
            'off_duty' => 'required|date'
        ]);

        $personId = $params['person_id'];
        $this->authorize('checkForOverlaps', [Timesheet::class, $personId]);

        $overlaps = Timesheet::findAllOverlapsForPerson($personId, $params['on_duty'], $params['off_duty'], $params['timesheet_id'] ?? null);
        if ($overlaps->isEmpty()) {
            return response()->json(['status' => 'success']);
        }

        $results = $overlaps->map(fn($row) => [
            'id' => $row->id,
            'position' => [
                'id' => $row->position_id,
                'title' => $row->position->title,
            ],
            'on_duty' => (string)$row->on_duty,
            'off_duty' => (string)$row->off_duty,
        ])->values();

        return response()->json(['status' => 'overlap', 'timesheets' => $results]);
    }

    /**
     * Report on people who may have gone on duty either early or late.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function earlyLateCheckins(): JsonResponse
    {
        $this->authorize('earlyLateCheckins', Timesheet::class);
        return response()->json(EarlyLateCheckInReport::execute($this->getYear()));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\PersonPosition;
use App\Models\PositionCredit;
use App\Models\Schedule;
use App\Models\Timesheet;
use App\Models\TimesheetLog;
use App\Models\TimesheetMissing;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use App\Exceptions\UnacceptableConditionException;

class TimesheetMissingController extends ApiController
{
    /**
     * Retrieve Timesheet Missing request records based on the given criteria.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $params = request()->validate([
            'person_id' => 'sometimes|integer',
            'year' => 'required|integer',
            'include_admin_notes' => 'sometimes|boolean',
        ]);

        $personId = $params['person_id'] ?? null;

        if ($personId) {
            $this->authorize('view', [TimesheetMissing::class, $personId]);
        } else {
            $this->authorize('viewAll', TimesheetMissing::class);
        }

        if ($params['include_admin_notes'] ?? false) {
            Gate::authorize('isTimesheetManager');
        }

        $rows = TimesheetMissing::findForQuery($params);

        if (!$rows->isEmpty()) {
            PositionCredit::warmYearCache($params['year'], array_unique($rows->pluck('position_id')->toArray()));
        }

        return $this->success($rows, null, 'timesheet_missing');
    }

    /**
     * Create a new timesheet missing entry
     *
     * @throws AuthorizationException|ValidationException
     */

    public function store(): JsonResponse
    {
        $timesheetMissing = new TimesheetMissing;
        $this->fromRestFiltered($timesheetMissing);

        $this->authorize('store', $timesheetMissing);

        $timesheetMissing->create_person_id = $this->user->id;
        if (empty($timesheetMissing->review_status)) {
            $timesheetMissing->review_status = TimesheetMissing::PENDING;
        }

        $person = $this->findPerson($timesheetMissing->person_id);

        return $this->saveAndMaybeCreateNewEntry($timesheetMissing, $person);
    }

    /**
     * Update a timesheet missing record
     *
     * @param TimesheetMissing $timesheetMissing
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws Exception
     */

    public function update(TimesheetMissing $timesheetMissing): JsonResponse
    {
        $this->authorize('update', $timesheetMissing);

        $person = $this->findPerson($timesheetMissing->person_id);

        $this->fromRestFiltered($timesheetMissing);

        if ($timesheetMissing->isDirty('review_status')) {
            $timesheetMissing->reviewed_at = now();
        }

        if ($this->user->id != $timesheetMissing->person_id) {
            $timesheetMissing->reviewer_person_id = $this->user->id;
        }

        if ($timesheetMissing->isDirty('additional_notes') && !$timesheetMissing->isDirty('review_status')) {
            $timesheetMissing->review_status = TimesheetMissing::PENDING;
        }

        return $this->saveAndMaybeCreateNewEntry($timesheetMissing, $person);
    }

    /**
     * Save the timesheet missing record, and create a new timesheet entry if create_entry is true.
     *
     * @param TimesheetMissing $timesheetMissing
     * @param Person $person
     * @return JsonResponse
     * @throws ValidationException
     */

    private function saveAndMaybeCreateNewEntry(TimesheetMissing $timesheetMissing, Person $person): JsonResponse
    {
        $createNew = ($timesheetMissing->review_status == TimesheetMissing::APPROVED && $timesheetMissing->create_entry);

        $newPositionId = null;
        $exists = $timesheetMissing->exists;
        if ($createNew) {
            // Verify the person may hold the position
            $newPositionId = $exists ? $timesheetMissing->new_position_id : $timesheetMissing->position_id;
            if (!PersonPosition::havePosition($person->id, $newPositionId)) {
                $timesheetMissing->addError('new_position_id', 'Person does not hold the position.');
                return $this->restError($timesheetMissing);
            }
        }

        try {
            DB::beginTransaction();
            if (!$timesheetMissing->save()) {
                DB::rollback();
                return $this->restError($timesheetMissing);
            }

            // Load up position title, reviewer callsigns in case of change.
            $timesheetMissing->loadRelationships(Gate::allows('isTimesheetManager'));

            if ($createNew) {
                $timesheet = new Timesheet([
                    'person_id' => $person->id,
                    'on_duty' => $exists ? $timesheetMissing->new_on_duty : $timesheetMissing->on_duty,
                    'off_duty' => $exists ? $timesheetMissing->new_off_duty : $timesheetMissing->off_duty,
                    'position_id' => $newPositionId,
                ]);

                if (!$timesheet->slot_id && $timesheet->position_id && $timesheet->on_duty) {
                    // Try to associate a slot with the sign on
                    $timesheet->slot_id = Schedule::findSlotIdSignUpByPositionTime($person->id, $timesheet->position_id, $timesheet->on_duty);
                }

                if (!$timesheet->save()) {
                    DB::rollback();
                    throw new UnacceptableConditionException('Failed to create new entry.');
                }

                $timesheet->log(TimesheetLog::CREATED,
                    [
                        'via' => TimesheetLog::VIA_MISSING_ENTRY,
                        'position_id' => $timesheet->position_id,
                        'on_duty' => (string)$timesheet->on_duty,
                        'off_duty' => (string)$timesheet->off_duty
                    ]
                );

                $year = $timesheet->on_duty->year;
                if ($year == current_year()) {
                    $event = PersonEvent::firstOrNewForPersonYear($person->id, $year);
                    if ($event->timesheet_confirmed) {
                        $event->auditReason = 'unconfirmed - new (missing) entry created';
                        $event->timesheet_confirmed = false;
                        $event->saveWithoutValidation();
                        TimesheetLog::record(TimesheetLog::UNCONFIRMED, $person->id, $this->user->id, null, 'new entry (from missing request) created.');
                    }
                }
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            throw $e;
        }

        return $this->success($timesheetMissing);
    }


    /**
     * Return a single timesheet missing record.
     *
     * @param TimesheetMissing $timesheetMissing
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(TimesheetMissing $timesheetMissing): JsonResponse
    {
        $this->authorize('view', [TimesheetMissing::class, $timesheetMissing->person_id]);
        $timesheetMissing->loadRelationships(Gate::allows('isTimesheetManager'));
        return $this->success($timesheetMissing);
    }

    /**
     * Delete a timesheet missing record.
     *
     * @param TimesheetMissing $timesheetMissing
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(TimesheetMissing $timesheetMissing): JsonResponse
    {
        $this->authorize('destroy', $timesheetMissing);
        $timesheetMissing->delete();
        return $this->restDeleteSuccess();
    }
}

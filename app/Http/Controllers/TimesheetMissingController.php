<?php

namespace App\Http\Controllers;

use App\Models\PersonEvent;
use App\Models\PersonPosition;
use App\Models\PositionCredit;
use App\Models\Schedule;
use App\Models\Timesheet;
use App\Models\TimesheetLog;
use App\Models\TimesheetMissing;
use Exception;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TimesheetMissingController extends ApiController
{
    public function index()
    {
        $params = request()->validate([
            'person_id' => 'sometimes|integer',
            'year' => 'required|integer',
        ]);

        $personId = $params['person_id'] ?? null;

        if ($personId) {
            $this->authorize('view', [TimesheetMissing::class, $personId]);
        } else {
            $this->authorize('viewAll', TimesheetMissing::class);
        }

        $rows = TimesheetMissing::findForQuery($params);

        if (!$rows->isEmpty()) {
            PositionCredit::warmYearCache($params['year'], array_unique($rows->pluck('position_id')->toArray()));
        }

        return $this->success($rows, null, 'timesheet_missing');
    }

    /*
     * Create a new timesheet missing entry
     */

    public function store()
    {
        $timesheetMissing = new TimesheetMissing;
        $this->fromRestFiltered($timesheetMissing);

        $this->authorize('store', $timesheetMissing);

        $timesheetMissing->create_person_id = $this->user->id;
        if (!$timesheetMissing->review_status) {
            $timesheetMissing->review_status = 'pending';
        }

        if ($timesheetMissing->save()) {
            $timesheetMissing->loadRelationships();
            return $this->success($timesheetMissing);
        }

        return $this->restError($timesheetMissing);
    }

    /*
     * Update a timesheet missing record
     */

    public function update(TimesheetMissing $timesheetMissing)
    {
        $this->authorize('update', $timesheetMissing);

        $person = $this->findPerson($timesheetMissing->person_id);

        $this->fromRestFiltered($timesheetMissing);

        if ($timesheetMissing->isDirty('review_status')
            || $timesheetMissing->isDirty('reviewer_notes')) {
            $timesheetMissing->reviewed_at = now();
        }

        if ($timesheetMissing->isDirty('notes')
            && $timesheetMissing->review_status != 'pending') {
            $timesheetMissing->review_status = 'pending';
        }

        $createNew = ($timesheetMissing->review_status == 'approved' && $timesheetMissing->create_entry);


        if ($createNew) {
            // Verify the person may hold the position
            if (!PersonPosition::havePosition($person->id, $timesheetMissing->new_position_id)) {
                throw new InvalidArgumentException('Person does not hold the position.');
            }
        }

        try {
            DB::beginTransaction();
            if (!$timesheetMissing->save()) {
                DB::rollback();
                return $this->restError($timesheetMissing);
            }

            // Load up position title, reviewer callsigns in case of change.
            $timesheetMissing->loadRelationships();

            if ($createNew) {
                $timesheet = new Timesheet([
                    'person_id' => $person->id,
                    'on_duty' => $timesheetMissing->new_on_duty,
                    'off_duty' => $timesheetMissing->new_off_duty,
                    'position_id' => $timesheetMissing->new_position_id,
                ]);

                if (!$timesheet->slot_id) {
                    // Try to associate a slot with the sign on
                    $timesheet->slot_id = Schedule::findSlotIdSignUpByPositionTime($person->id, $timesheet->position_id, $timesheet->on_duty);
                }

                if (!$timesheet->save()) {
                    DB::rollback();
                    throw new InvalidArgumentException('Failed to create new entry.');
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

    /*
     * Return a single timesheet missing record.
     */

    public function show(TimesheetMissing $timesheetMissing)
    {
        $this->authorize('view', [TimesheetMissing::class, $timesheetMissing->person_id]);
        $timesheetMissing->loadRelationships();
        return $this->success($timesheetMissing);
    }

    /*
     * Delete a timesheet missing record.
     */

    public function destroy(TimesheetMissing $timesheetMissing)
    {
        $this->authorize('destroy', $timesheetMissing);
        $timesheetMissing->delete();
        return $this->restDeleteSuccess();
    }
}

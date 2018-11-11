<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;

use App\Helpers\SqlHelper;
use App\Models\PositionCredit;
use App\Models\Timesheet;
use App\Models\TimesheetLog;
use App\Models\TimesheetMissing;

class TimesheetMissingController extends ApiController
{
    public function index()
    {
        $params = request()->validate([
            'person_id' => 'sometimes|integer',
            'year'      => 'required|integer',
        ]);

        if (isset($params['person_id'])) {
            $this->authorize('view', [ TimesheetMissing::class, $params['person_id'] ]);
        } else if ($this->user->hasRole([ Role::ADMIN, Role::TIMESHEET_MANAGEMENT ])) {
            $this->notPermitted('You are not permitted to view all the timesheet missing requests.');
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

        if ($timesheetMissing->isDirty('reviewer_status')
        || $timesheetMissing->isDirty('reviewer_notes')) {
            $timesheetMissing->reviewed_at = SqlHelper::now();
        }

        if (!$timesheetMissing->save()) {
            return $this->restError($timesheetMissing);
        }

        // Load up position title, reviewer callsigns in case of change.
        $timesheetMissing->loadRelationships();

        return $this->success($timesheetMissing);
    }

    /*
     * Return a single timesheet missing record.
     */

    public function show(TimesheetMissing $timesheetMissing)
    {
        $this->authorize('view', [ TimesheetMissing::class, $timesheetMissing->person_id ]);
        $timesheet->loadRelationships();
        return $this->success($timesheetMissing);
    }

    /*
     * Delete a timesheet missing record.
     */

     public function destroy(TimesheetMissing $timesheetMissing)
     {
         $this->authorize('destroy',$timesheetMissing);
         $timesheetMissing->delete();

         return $this->restDeleteSuccess();
     }
}

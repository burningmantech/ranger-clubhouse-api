<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\ApiController;

use App\Models\Slot;
use App\Models\PersonSlot;
use App\Models\PositionCredit;
use App\Models\Role;

use Carbon\Carbon;

class SlotController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->authorize('index', Slot::class);

        $query = request()->validate([
            'year'        => 'required|digits:4',
            'type'        => 'sometimes|string',
            'position_id' => 'sometimes|digits'
        ]);

        $rows = Slot::findForQuery($query);

        if (!$rows->isEmpty()) {
            // Warm the position credit cache
            PositionCredit::warmYearCache($query['year'], array_unique($rows->pluck('position_id')->toArray()));
        }

        return $this->success($rows, null, 'slot');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorize('store', Slot::class);

        $slot = new Slot;
        $this->fromRest($slot);

        if (!$this->validateRestrictions($slot)) {
            return $this->restError($slot);
        }

        if (!$slot->save()) {
            return $this->restError($slot);
        }

        $this->log('slot-create', 'create', [ 'slot' => $slot ]);

        // Return the position & trainer_slot info
        $slot->loadRelationships();

        return $this->success($slot);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Slot  $slot
     * @return \Illuminate\Http\Response
     */
    public function show(Slot $slot)
    {
        return $this->success($slot);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Slot  $slot
     * @return \Illuminate\Http\Response
     */
    public function update(Slot $slot)
    {
        $this->authorize('update', Slot::class);
        $this->fromRest($slot);

        if (!$this->validateRestrictions($slot)) {
            return $this->restError($slot);
        }

        $changes = $slot->getChangedValues();
        if (!$slot->save()) {
            return $this->restError($slot);
        }

        if (!empty($changes)) {
            $changes['slot_id'] = $slot->id;
            $this->log('slot-update', 'update', $changes);
        }

        // In case position or trainer_slot changed.
        $slot->loadRelationships();

        return $this->success($slot);
    }

    public function bulkUpdate(Request $request)
    {
        $this->authorize('update', Slot::class);

        $params = request()->validate([
            'ids'   => 'required|array',
            'attributes' => 'required|array'
        ]);

        $attributes = $params['attributes'];
        $slots = Slot::whereIn('id', $params['ids'])->get();

        DB::transaction(function () use ($slots, $attributes) {
            foreach ($slots as $slot) {
                $slot->fill($attributes);
                $changes = $slot->getChangedValues();
                $slot->save();
                if (!empty($changes)) {
                    $changes['slot_id'] = $slot->id;
                    $this->log('slot-update', 'bulk update', $changes);
                }
            }
        });

        return $this->success($slots, null, 'slots');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Slot  $slot
     * @return \Illuminate\Http\Response
     */
    public function destroy(Slot $slot)
    {
        $this->authorize('delete', Slot::class);

        $slot->delete();
        PersonSlot::deleteForSlot($slot->id);

        $this->log('slot-delete', 'delete', [ 'slot' => $slot ]);

        return $this->restDeleteSuccess();
    }

    /*
     * Return people signed up for a given Slot
     */

    public function people(Slot $slot)
    {
        $signUps = Slot::findSignUps($slot->id);
        return response()->json([ 'people' => $signUps]);
    }

    /*
     * Return how many years the slots span
     */

    public function years()
    {
        return response()->json([ 'years' => Slot::findYears() ]);
    }

    /*
     * Partially validate a slot based on restrictions
     *
     * - If the slot is time restricted (begins within pre-event period, and not
     * an approved position, then only an Admin may be allowed to create or update.
     */

    private function validateRestrictions($slot)
    {
        if (!$slot->isPreEventRestricted()) {
            // Either falls outside the pre-event period, or has an approved position
            return true;
        }

        if ($this->userHasRole(Role::ADMIN)) {
            // Bow before your slot master!
            return true;
        }

        $slot->addError('begins', 'Slot is a non-training position and the start time falls within the pre-event period. Action requires Admin privileges.');
        return false;
    }

    /*
     * Report on the Dirt Shifts - used for the shift Lead Report
     */

    public function dirtShiftTimes()
    {
        $params = request()->validate([
             'year' => 'required|integer'
         ]);

         return response()->json([ 'shifts' => Slot::retrieveDirtTimes($params['year'])]);
    }

    /*
     * Shift Lead report on sign ups
     */

    public function shiftLeadReport()
    {
        $params = request()->validate([
             'shift_start' => 'required|date',
             'shift_duration'   => 'required|integer'
         ]);

         $shiftStart = new Carbon($params['shift_start']);
         $shiftDuration = $params['shift_duration'];
         $shiftEnd = $shiftStart->clone()->addSeconds($shiftDuration);

         $info = [
             // Positions and head counts
             'incoming_positions' => Slot::retrievePositionsScheduled($shiftStart, $shiftEnd, false),
             'below_min_positions' => Slot::retrievePositionsScheduled($shiftStart, $shiftEnd, true),

             // People signed up
             'non_dirt_signups' => Slot::retrieveRangersScheduled($shiftStart, $shiftEnd, 'non-dirt'),
             'command_staff_signups' => Slot::retrieveRangersScheduled($shiftStart, $shiftEnd, 'command'),
             'dirt_signups' => Slot::retrieveRangersScheduled($shiftStart, $shiftEnd, 'dirt+green'),

             // Green Dot head counts
             'green_dot_total'  => Slot::countGreenDotsScheduled($shiftStart, $shiftEnd),
             'green_dot_females'  => Slot::countGreenDotsScheduled($shiftStart, $shiftEnd, true),
         ];

         return response()->json($info);
    }
}

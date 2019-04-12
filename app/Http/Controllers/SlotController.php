<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\ApiController;

use App\Models\Slot;
use App\Models\PersonSlot;
use App\Models\PositionCredit;
use App\Models\Role;

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

        if (!$slot->save()) {
            return $this->restError($slot);
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
                $slot->save();
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
}

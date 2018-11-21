<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Helpers\DateHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use App\Models\Slot;

//
// Meta Model
// This will combined slot & position and maybe person_slot.
//

class ScheduleException extends \Exception { };

class Schedule extends ApiModel
{

    // laravel will pull from $this->attributes
    protected $fillable = [
        'position_id',
        'position_title',
        'position_type',
        'slot_id',
        'slot_active',
        'slot_begins',
        'slot_ends',
        'slot_description',
        'slot_signed_up',
        'slot_max',
        'slot_url',
        'person_assigned',
        'trainers',
        'slot_begins_time',
        'slot_ends_time',
    ];

    // And the rest are calculated
    protected $appends = [
        'slot_duration',
        'year',
        'credits'
    ];

    protected $dates = [
        'slot_begins',
        'slot_ends'
    ];

    public static function findForQuery($query)
    {
        if (empty($query['year'])) {
            throw new \InvalidArgumentException('Missing year parameter');
        }

        $year = $query['year'];

        $personId = !empty($query['person_id']) ? $query['person_id'] : null;
        $signups = !empty($query['signups']);

        $selectColumns = [
            'slot.id as id',
            'position.id as position_id',
            'position.title AS position_title',
            'position.type AS position_type',
            'slot.begins AS slot_begins',
            'slot.ends AS slot_ends',
            'slot.description AS slot_description',
            'slot.signed_up AS slot_signed_up',
            'slot.max AS slot_max',
            'slot.url AS slot_url',
            'slot.active as slot_active',
            'trainer_slot.signed_up AS trainer_count',
            DB::raw('UNIX_TIMESTAMP(slot.begins) as slot_begins_time'),
            DB::raw('UNIX_TIMESTAMP(slot.ends) as slot_ends_time'),
            DB::raw('IF(slot.begins < NOW(), TRUE, FALSE) as has_started'),
            DB::raw('IF(slot.ends < NOW(), TRUE, FALSE) as has_ended')
        ];

        // Is this a simple schedule find for a person?
        if ($personId && !$signups) {
            $sql = DB::table('person_slot')
                    ->where('person_slot.person_id', $personId)
                    ->join('slot', 'slot.id', '=', 'person_slot.slot_id');
        } else {
            // Retrieve all slots
            $sql = DB::table('slot');

            // .. and find out which slots a person has signed up for
            if ($signups) {
                $selectColumns[] = DB::raw('IF(person_slot.person_id IS NULL,FALSE,TRUE) AS person_assigned');
                $sql = $sql->leftJoin('person_slot', function ($join) use ($personId) {
                    $join->where('person_slot.person_id', $personId)
                          ->on('person_slot.slot_id', 'slot.id');
                })->join('person_position', function ($join) use ($personId) {
                    $join->where('person_position.person_id', $personId)
                         ->on('person_position.position_id', 'slot.position_id');
                });
            }
        }

        if (isset($query['type'])) {
            $sql = $sql->where('position.type', $query['type']);
        }

        $rows = $sql->select($selectColumns)
            ->whereYear('slot.begins', $year)
            ->join('position', 'position.id', '=', 'slot.position_id')
            ->leftJoin('slot as trainer_slot', 'trainer_slot.id', '=', 'slot.trainer_slot_id')
            ->orderBy('slot.begins', 'asc', 'position.title', 'asc', 'slot.description', 'asc')
            ->get()->toArray();

        // return an array of Schedule loaded from the rows
        return Schedule::hydrate($rows);
    }

    //
    // Add a person to a slot, and update the signed up count.
    // Also verify the signup does not already exist,the sign up
    // count is not maxed out, the person holds the slot's position,
    // and the slot exists.
    //
    // An array is returned with one of the following values:
    //   - Success full sign up
    //     status='success', signed_up=the signed up count include this person
    //   - Slot is maxed out
    //      status='full'
    //   - Person does not hold slot position
    //      status='no-position'
    //   - Person is already signed up for the slot
    //      status='exists'
    //
    // @param int $personId person to add
    // @param int $slotId slot to add to
    // @param bool $force set true if to ignore the signup limit
    // @return array
    //

    public static function addToSchedule($personId, $slotId, $force=false): array
    {
        if (PersonSlot::haveSlot($personId, $slotId)) {
            return [ 'status' => 'exists' ];
        }

        $addForced = false;

        try {
            DB::beginTransaction();

            $slot = Slot::where('id', $slotId)->lockForUpdate()->first();
            if (!$slot) {
                throw new ScheduleException('no-slot');
            }

            // Slot must be activated in order to allow signups
            if (!$slot->active) {
                throw new ScheduleException('not-active');
            }

            // You must hold the position
            if (!PersonPosition::havePosition($personId, $slot->position_id)) {
                throw new ScheduleException('no-position');
            }

            // Cannot exceed sign up limit unless it is forced.
            if ($slot->signed_up >= $slot->max) {
                if (!$force) {
                    throw new ScheduleException('full');
                }

                $addForced = true;
            }

            $ps = new PersonSlot([
                'person_id' => $personId,
                'slot_id'   => $slot->id
            ]);
            $ps->save();

            $slot->signed_up += 1;
            $slot->save();

            DB::commit();
        } catch (ScheduleException $e) {
            DB::rollback();
            return [ 'status' => $e->getMessage() ];
        }

        return [ 'status' => 'success', 'signed_up' => $slot->signed_up, 'forced' => $addForced ];
    }

    //
    // Remove a person from a slot
    //

    public static function deleteFromSchedule($personId, $personSlotId): array
    {

        $personSlot = PersonSlot::where([
                    [ 'person_id', $personId],
                    [ 'slot_id', $personSlotId]
                ])->firstOrFail();

        $slot = $personSlot->belongsTo(Slot::class, 'slot_id')
                            ->lockForUpdate()->firstOrFail();

        try {
            DB::beginTransaction();
            $personSlot->delete();
            if ($slot->signed_up > 0) {
                $slot->update(['signed_up' => ($slot->signed_up - 1)]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        return [ 'status' => 'success', 'signed_up' => $slot->signed_up ];
    }

    public function getSlotDurationAttribute()
    {
        return $this->slot_ends_time - $this->slot_begins_time;
    }

    public function getYearAttribute()
    {
        return Carbon::parse($this->slot_begins)->year;
    }

    public function getCreditsAttribute()
    {
        return PositionCredit::computeCredits($this->position_id, $this->slot_begins_time, $this->slot_ends_time, $this->year);
    }
}

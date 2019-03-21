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

    const SHIFT_STARTS_WITHIN = 30; // A shift starts within X minutes

    public static function findForQuery($query)
    {
        if (empty($query['year'])) {
            throw new \InvalidArgumentException('Missing year parameter');
        }

        $year = $query['year'];

        $personId = $query['person_id'] ?? null;
        $shiftsAvailable = $query['shifts_available'] ?? false;

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
        if ($personId && !$shiftsAvailable) {
            $sql = DB::table('person_slot')
                    ->where('person_slot.person_id', $personId)
                    ->join('slot', 'slot.id', '=', 'person_slot.slot_id');
        } else {
            // Retrieve all slots
            $sql = DB::table('slot');

            // .. and find out which slots a person has signed up for
            if ($shiftsAvailable) {
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

    public static function addToSchedule($personId, $slot, $force=false): array
    {
        if (PersonSlot::haveSlot($personId, $slot->id)) {
            return [ 'status' => 'exists', 'signed_up' => $slot->signed_up ];
        }

        $addForced = false;
        $signedUp = 0;
        $max = 0;

        try {
            DB::beginTransaction();

            // Re-read the slot for update.
            $updateSlot = Slot::where('id', $slot->id)->lockForUpdate()->first();
            if (!$updateSlot) {
                // shouldn't happen but ya never know...
                throw new ScheduleException('no-slot');
            }

            // Slot must be activated in order to allow signups
            if (!$updateSlot->active) {
                throw new ScheduleException('not-active');
            }

            // You must hold the position
            if (!PersonPosition::havePosition($personId, $updateSlot->position_id)) {
                throw new ScheduleException('no-position');
            }

            // Cannot exceed sign up limit unless it is forced.
            if ($updateSlot->signed_up >= $updateSlot->max) {
                if (!$force) {
                    throw new ScheduleException('full');
                }

                $addForced = true;
            }

            // looks up! sign up the person
            $ps = new PersonSlot([
                'person_id' => $personId,
                'slot_id'   => $updateSlot->id
            ]);
            $ps->save();

            $updateSlot->signed_up += 1;
            $updateSlot->save();
            DB::commit();

            return [
                'status'    => 'success',
                'signed_up' => $updateSlot->signed_up,
                'forced'    => $addForced,
            ];
        } catch (ScheduleException $e) {
            DB::rollback();
            return [ 'status' => $e->getMessage(), 'signed_up' => $updateSlot->signed_up ];
        }
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

    /*
     * Does a person have multiple enrollments for the same position (aka Training)
     * and if so what are the enrollments?
     */

    public static function haveMultipleEnrollments($personId, $positionId, $year, & $enrollments) {
        $slotIds = PersonSlot::where('person_slot.person_id', $personId)
                    ->join('slot', function($query) use ($positionId, $year) {
                        $query->whereRaw('slot.id=person_slot.slot_id');
                        $query->where('slot.position_id', $positionId);
                        $query->whereYear('slot.begins', $year);
                    })->get()->pluck('slot_id');

        if ($slotIds->isEmpty()) {
            $enrollments = null;
            return false;
        }

        $enrollments = Slot::whereIn('id', $slotIds)->with('position:id,title')->get();
        return true;
    }

    public static function retrieveStartingSlotsForPerson($personId)
    {
        $rows =  PersonSlot::join('slot', function($query) {
                    $query->whereRaw('slot.id=person_slot.slot_id');
                    $query->whereRaw('slot.begins BETWEEN DATE_SUB(NOW(), INTERVAL ? MINUTE) AND DATE_ADD(NOW(), INTERVAL ? MINUTE)',
                        [ self::SHIFT_STARTS_WITHIN, self::SHIFT_STARTS_WITHIN]);
            })
            ->where('person_id', $personId)
            ->with('slot.position:id,title')->get();

            return $rows->map(function($row) {
                return [
                    'slot_id'   => $row->slot_id,
                    'slot_description' => $row->slot->description,
                    'position_id' => $row->slot->position_id,
                    'position_title' => $row->slot->position->title,
                    'slot_begins' => (string) $row->slot->begins,
                    'slot_ends' => (string) $row->slot->ends,
                ];
            });
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

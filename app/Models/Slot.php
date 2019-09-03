<?php

namespace App\Models;

use App\Models\ApiModel;
use Illuminate\Support\Facades\DB;
use App\Models\Position;
use App\Models\EventDate;

use Carbon\Carbon;

class Slot extends ApiModel
{
    protected $table = 'slot';

    protected $fillable = [
        'active',
        'begins',
        'description',
        'ends',
        'max',
        'min',
        'position_id',
        'signed_up',
        'trainer_slot_id',
        'url',
        'begins_time',
        'ends_time',
        'has_started',
        'has_ended'
    ];

    protected $appends = [
        'credits'
    ];

    protected $rules = [
        'begins'      => 'required|date|before:ends',
        'description' => 'required|string|max:40',
        'url'         => 'sometimes|string|max:512|nullable',
        'ends'        => 'required|date|after:begins',
        'max'         => 'required|integer',
        'position_id' => 'required|integer',
        'trainer_slot_id' => 'nullable|integer'
    ];

    // related tables to be loaded with row
    const WITH_POSITION_TRAINER = [
        'position:id,title,type',
        'trainer_slot:id,position_id,description,begins,ends',
        'trainer_slot.position:id,title'
    ];

    protected $dates = [
        'ends',
        'begins',
    ];

    public function position() {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function trainer_slot() {
        return $this->belongsTo(Slot::class, 'trainer_slot_id');
    }

    public static function findForQuery($query) {
        $sql = self::with(self::WITH_POSITION_TRAINER);

        if (isset($query['year'])) {
            $sql = $sql->whereYear('begins', $query['year']);
        }

        if (isset($query['type'])) {
            $sql = $sql->where('type', $query['type']);
        }

        if (isset($query['position_id'])) {
            $sql = $sql->where('position_id', $query['position_id']);
        }

        return $sql->get();
    }

    public static function findBase($slotId) {
        return self::select(
                'slot.*',
                DB::raw('IF(slot.begins < NOW(), TRUE, FALSE) as has_started'),
                DB::raw('IF(slot.ends < NOW(), TRUE, FALSE) as has_ended')
            )
            ->where('id', $slotId)->with(self::WITH_POSITION_TRAINER);
    }

    public static function find($slotId) {
        return self::findBase($slotId)->first();
    }

    public static function findOrFail($slotId) {
        return self::findBase($slotId)->firstOrFail();
    }

    public static function findWithSignupsForYear($year)
    {
        return self::whereYear('begins', $year)
                ->where('signed_up', '>', 0)
                ->with('position:id,title')
                ->orderBy('begins')
                ->get();
    }

    public static function findSignUps($slotId) {
        return DB::table('person_slot')
            ->select('person.id', 'person.callsign')
            ->join('person', 'person.id', '=', 'person_slot.person_id')
            ->where('person_slot.slot_id', $slotId)
            ->orderBy('person.callsign', 'asc')
            ->get();
    }

    public static function findYears() {
        return self::selectRaw('YEAR(begins) as year')
                ->groupBy(DB::raw('YEAR(begins)'))
                ->pluck('year')->toArray();
    }

    public static function retrieveDirtTimes($year)
    {
        $rows = DB::table('slot')
            ->select('position_id','begins', 'ends', DB::raw('timestampdiff(second, begins, ends) as duration'))
            ->whereYear('begins', $year)
            ->whereIn('position_id', [ Position::DIRT, Position::DIRT_PRE_EVENT, Position::DIRT_POST_EVENT])
            ->orderBy('begins')
            ->get();

        foreach ($rows as $row) {
            $start = new Carbon($row->begins);

            if ($start->timestamp % 3600) {
                // If it's not on an hour boundary, bump it by 15 minutes
                $start->addMinutes(15);
                $row->duration -= 30*60;
            }

            $row->shift_start = (string) $start;
        }

        return $rows;
    }

    public static function retrievePositionsScheduled(Carbon $shiftStart, Carbon $shiftEnd, $belowMin)
    {
        $sql = DB::table('slot')
        ->select(
            'slot.begins AS slot_begins',
            'slot.ends AS slot_ends',
            DB::raw('TIMESTAMPDIFF(second,slot.begins,slot.ends) as slot_duration'),
            DB::raw('IF(slot.begins < NOW() AND slot.ends > NOW(), TIMESTAMPDIFF(second, NOW(), ends),0) as remaining'),
            'slot.description AS description',
            'slot.signed_up AS signed_up',
            'slot.min AS min',
            'slot.max AS max',
            'position.title AS title',
            'position.type AS position_type'
        )->join('position', 'position.id', '=', 'slot.position_id')
        ->orderBy('position.title')
        ->orderBy('slot.begins');

        self::buildShiftRange($sql, $shiftStart, $shiftEnd, 45);

        if ($belowMin) {
            $sql->whereIn('position.type', [ 'Frontline', 'Command' ]);
            $sql->whereRaw('slot.signed_up < slot.min');
        } else {
            $sql->where('position.type', 'Frontline');
        }

        return $sql->get();
    }

    public static function retrieveRangersScheduled(Carbon $shiftStart, Carbon $shiftEnd, $type)
    {
        $year = $shiftStart->year;

        $sql = DB::table('slot')
        ->select(
            'slot.id AS slot_id',
            'slot.begins AS slot_begins',
            'slot.ends AS slot_ends',
            'slot.description AS description',
            'slot.signed_up AS signed_up',
            'person.callsign AS callsign',
            'person.gender AS gender',
            'person.id AS person_id',
            'person.vehicle_blacklisted AS vehicle_blacklisted',
            'person.vehicle_paperwork AS vehicle_paperwork',
            'person.vehicle_insurance_paperwork AS vehicle_insurance_paperwork',
            'position.title AS position_title',
            'position.short_title AS short_title',
            'position.type AS position_type',
            'position.id AS position_id',
            DB::raw('(SELECT COUNT(DISTINCT YEAR(on_duty)) FROM timesheet WHERE person_id = person.id) AS years')
        )
        ->join('person_slot', 'person_slot.slot_id','=','slot.id')
        ->join('person', 'person.id', '=', 'person_slot.person_id')
        ->join('position', 'position.id', '=', 'slot.position_id')
        ->orderBy('slot.begins')
        ->orderByRaw('CASE WHEN position.id='.Position::DIRT_SHINY_PENNY.' THEN "1111" ELSE position.title END DESC');

        self::buildShiftRange($sql, $shiftStart, $shiftEnd, 45);

        switch ($type) {
        case 'non-dirt':
            $sql->whereNotIn('slot.position_id', [
                Position::DIRT, Position::DIRT_PRE_EVENT, Position::DIRT_SHINY_PENNY,
                Position::DIRT_GREEN_DOT, Position::GREEN_DOT_MENTOR, Position::GREEN_DOT_MENTEE
            ])->where('position.type', '=', 'Frontline');
            break;

        case 'command':
            $sql->where('position.type', 'Command');
            break;

        case 'dirt+green':
            $sql->whereIn('slot.position_id', [
                Position::DIRT, Position::DIRT_PRE_EVENT, Position::DIRT_SHINY_PENNY,
                Position::DIRT_GREEN_DOT, Position::GREEN_DOT_MENTOR, Position::GREEN_DOT_MENTEE
            ])->orderBy('years', 'desc');
            break;
        }

        $sql->orderBy('callsign');

        $people = $sql->get();

        $personIds = $people->pluck('person_id')->toArray();

        $peoplePositions = DB::table('person_position')
                ->select('person_position.person_id', 'position.short_title', 'position.id as position_id')
                ->join('position', 'position.id', '=', 'person_position.position_id')
                ->where('position.on_sl_report', 1)
                ->whereNotIn('position.id', [ Position::DIRT, Position::DIRT_SHINY_PENNY])    // Don't need report on dirt
                ->whereIn('person_position.person_id', $personIds)
                ->get()
                ->groupBy('person_id');

        foreach ($people as $person) {
            $person->gender = Person::summarizeGender($person->gender);
            $positions = $peoplePositions[$person->person_id] ?? null;

            $positionId = $person->position_id;

            $person->is_greendot_shift = ($positionId == Position::DIRT_GREEN_DOT
                                || $positionId == Position::GREEN_DOT_MENTOR);

            $person->slot_begins_day_before = (new Carbon($person->slot_begins))->day != $shiftStart->day;
            $person->slot_ends_day_after = (new Carbon($person->slot_ends))->day != $shiftStart->day;

            if ($positions) {
                $person->is_troubleshooter = $positions->contains('position_id', Position::TROUBLESHOOTER);
                $person->is_rsl = $positions->contains('position_id', Position::RSC_SHIFT_LEAD);
                $person->is_ood = $positions->contains('position_id', Position::OOD);

                // Determine if the person is a GD AND if they have been trained this year.
                $haveGDPosition = $positions->contains(function ($row) {
                    $pid = $row->position_id;
                    return ($pid == Position::DIRT_GREEN_DOT || $pid == Position::GREEN_DOT_MENTOR);
                });

                // The check for the mentee shift is a hack to prevent past years from showing
                // a GD Mentee as a qualified GD.
                if ($haveGDPosition ) {
                    $person->is_greendot = TraineeStatus::didPersonPassForYear($person->person_id, Position::GREEN_DOT_TRAINING, $year);
                    if (!$person->is_greendot || ($positionId == Position::GREEN_DOT_MENTEE)) {
                        $person->is_greendot = false; // just in case
                        // Not trained - remove the GD positions
                        $positions = $positions->filter(function ($row) {
                            $pid = $row->position_id;
                            return !($pid == Position::DIRT_GREEN_DOT
                                || $pid == Position::GREEN_DOT_MENTOR);
                        });
                    }
                }

                $person->positions = $positions->pluck('short_title')->toArray();
            }
        }

        return $people;
    }

    public static function countGreenDotsScheduled($shiftStart, $shiftEnd, $femaleOnly=false)
    {
        $sql = DB::table('slot')
        ->join('person_slot', 'person_slot.slot_id','=','slot.id')
        ->join('person', 'person.id', '=', 'person_slot.person_id')
        ->whereIn('slot.position_id', [ Position::DIRT_GREEN_DOT, Position::GREEN_DOT_MENTOR ]);

        self::buildShiftRange($sql, $shiftStart, $shiftEnd, 90);

        if ($femaleOnly) {
            $sql->where(function ($q) {
                $q->whereRaw('lower(LEFT(person.gender,1)) = "f"');
                $q->orWhereRaw('person.gender REGEXP "[[:<:]](female|girl|femme|lady|she|her|woman|famale|fem)[[:>:]]"');
            });
        }

        return $sql->count();
    }

    public static function buildShiftRange($sql, $shiftStart, $shiftEnd, $minAfterStart)
    {
        $sql->where(function ($q) use ($shiftStart, $shiftEnd, $minAfterStart) {
            // all slots starting before and ending on or start the range
            $q->where([
                [ 'begins', '<=', $shiftStart],
                [ 'ends', '>=', $shiftEnd ]
            ]);
            // or starting within 1 hour before
            $q->orWhere([
                [ 'begins', '>=', $shiftStart->clone()->addHours(-1) ],
                [ 'begins', '<', $shiftEnd->clone()->addHours(-1) ]
            ]);

            // or.. starting within after X minutes
            $q->orWhere([
                [ 'ends', '>=', $shiftStart->clone()->addMinutes($minAfterStart) ],
                [ 'ends', '<=', $shiftEnd ]
            ]);
        });
    }

    public function getPositionTitleAttribute() {
        return $this->position ? $this->position->title : "Position #{$this->position_id}";
    }

    public function loadRelationships() {
        $this->load(self::WITH_POSITION_TRAINER);
    }

    public function getCreditsAttribute()
    {
        return PositionCredit::computeCredits($this->position_id, $this->begins->timestamp, $this->ends->timestamp, $this->begins->year);
    }

    public function isTraining() {
        $position = $this->position;
        if ($position == null) {
            return false;
        }

        return $position->type == "Training" && stripos($position->title, "trainer") === false;
    }

    public function isArt() {
        return ($this->position_id != Position::DIRT_TRAINING);
    }

    /*
     * Humanized datetime formats - for sending emails
     */

     public function getBeginsHumanFormatAttribute() {
         return $this->begins->format('l M d Y @ H:i');
     }

     public function getEndsHumanFormatAttribute() {
         return $this->ends->format('l M d Y @ H:i');
     }

     /*
      * Check to see if the slot begins within the pre-event period and
      * is not a training slot
      */

      public function isPreEventRestricted() {
          if (!$this->begins || !$this->position_id) {
              return false;
          }

          $eventDate = EventDate::findForYear($this->begins->year);

          if (!$eventDate || !$eventDate->pre_event_slot_start || !$eventDate->pre_event_slot_end) {
              return false;
          }

          if ($this->begins->lt($eventDate->pre_event_slot_start) || $this->begins->gte($eventDate->pre_event_slot_end)) {
              // Outside of Pre-Event period
              return false;
          }

          return !$this->isTraining();
      }

    /*
     * Find and return the session part number if it exists.
     */

    public function sessionGroupPart() {
        $matched = preg_match('/\bPart (\d)\b/i', $this->description, $matches);

        if (!$matched) {
            return 0;
        } else {
            return (int) $matches[1];
        }
    }

    /*
     * Grab the session name minus any "- Part N" suffix.
     *
     * "Pre-Event - Part 1" becomes "Pre-Event"
     */

    public function sessionGroupName() {
        $matched = preg_match('/^(.*?)\s*-?\s*\bPart\s*\d\s*$/', $this->description, $matches);
        return $matched ? $matches[1] : null;
    }

    /*
     * Is the slot part of a session group?
     */
    public function isPartOfSessionGroup($slot) {
        $ourPart = $this->sessionGroupPart();
        $theirPart = $slot->sessionGroupPart();

        return ($ourPart && $theirPart && $slot->sessionGroupName() == $this->sessionGroupName());
    }
}

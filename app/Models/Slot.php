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
        'ends_time'
    ];

    protected $appends = [
        'credits'
    ];

    protected $rules = [
        'begins'      => 'required|date|before:ends',
        'description' => 'required|string',
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
        return self::where('id', $slotId)->with(self::WITH_POSITION_TRAINER);
    }

    public static function find($slotId) {
        return self::findBase($slotId)->first();
    }

    public static function findOrFail($slotId) {
        return self::findBase($slotId)->firstOrFail();
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
}

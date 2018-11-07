<?php

namespace App\Models;

use App\Models\ApiModel;
use Illuminate\Support\Facades\DB;
use App\Models\Position;
use Carbon\Carbon;

class Slot extends ApiModel
{
    protected $table = 'slot';

    protected $fillable = [
        'begins',
        'ends',
        'position_id',
        'description',
        'signed_up',
        'max',
        'url',
        'trainer_slot_id',
        'min',
        'active'
    ];

    protected $appends = [
        'credits'
    ];

    protected $rules = [
        'position_id' => 'required|integer',
        'description' => 'required|string',
        'begins'      => 'required|date|before:ends',
        'ends'        => 'required|date|after:begins',
        'max'         => 'required|integer',
        'trainer_slot_id' => 'nullable|integer'
    ];

    // related tables to be loaded with row
    const WITH_POSITION_TRAINER = [
        'position:id,title',
        'trainer_slot:id,position_id,description,begins',
        'trainer_slot.position:id,title'
    ];

    protected $casts = [
        'begins'    => 'datetime',
        'ends'      => 'datetime'
    ];

    public function position() {
        return $this->belongsTo('App\Models\Position');
    }

    public function trainer_slot() {
        return $this->belongsTo('\App\Models\Slot');
    }

    public static function findForQuery($query) {
        $sql = self::with(self::WITH_POSITION_TRAINER);

        if (isset($query['year'])) {
            $sql = $sql->whereRaw('YEAR(begins)=?', $query['year']);
        }

        if (isset($query['type'])) {
            $sql = $sql->where('type', $query['type']);
        }

        if (isset($query['position_id'])) {
            $sql = $sql->where('position_id', $query['position_id']);
        }

        return $sql->get();
    }

    public static function find($slotId) {
        return self::where('id', $slotId)->with(self::WITH_POSITION_TRAINER)->firstOrFail();
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
        return $this->position ? $this->position->title : "Postiion #{$this->position_id}";
    }

    public function loadRelationships() {
        $this->load(self::WITH_POSITION_TRAINER);
    }

    public function getCreditsAttribute()
    {
        return PositionCredit::computeCredits($this->position_id, $this->begins, $this->ends);
    }
}

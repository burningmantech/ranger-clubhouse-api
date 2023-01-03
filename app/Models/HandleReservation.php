<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Collection;

class HandleReservation extends ApiModel
{
    use HasFactory;

    protected $table = 'handle_reservation';
    protected $auditModel = true;
    public $timestamps = true;

    // Handle reservation types
    const BRC_TERM = 'brc_term';
    const DECEASED_PERSON = 'deceased_person';
    const DISMISSED_PERSON = 'dismissed_person';
    const RADIO_JARGON = 'radio_jargon';
    const RANGER_TERM = 'ranger_term';
    const SLUR = 'slur';
    const TWII_PERSON = 'twii_person';
    const UNCATEGORIZED = 'uncategorized';

    protected $fillable = [
        'handle',
        'reservation_type',
        'start_date',
        'end_date',
        'reason',
    ];

    protected $casts = [
        'start_date' => 'datetime:Y-m-d',
        'end_date' => 'datetime:Y-m-d',
    ];

    protected $rules = [
        'handle' => 'required|string|max:100',
        'reservation_type' => 'required|string|max:100',
        'start_date' => 'required|date:Y-m-d',
        'end_date' => 'sometimes|date:Y-m-d|after:start_date|nullable',
        'reason' => 'sometimes|string|max:255',
    ];

    public static function findAll(): Collection
    {
        return HandleReservation::all()->sortBy('handle', SORT_NATURAL)->values();
    }
}

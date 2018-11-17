<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Models\Position;

class PersonPosition extends ApiModel
{
    protected $table = 'person_position';

    protected $fillable = [
        'person_id',
        'position_id'
    ];

    public static function havePosition($personId, $positionId) {
        return self::where('person_id', $personId)
                   ->where('position_id', $positionId)->exists();
    }

    /*
     * Return a list of positions that need training
     */

    public static function findTrainingRequired($personId) {
        return self::select('position.id as position_id', 'position.title', 'position.training_position_id')
                ->join('position', 'position.id', '=', 'person_position.position_id')
                ->where('person_id', $personId)
                ->where(function($query) {
                    $query->whereNotNull('position.training_position_id')
                    ->orWhere('position.id', Position::DIRT);
                })->get();
    }

    /*
     * Return the positions held for a user
     */

     public static function findForPerson($personId)
     {
         return self::select('position.id', 'position.title', 'position.training_position_id')
                ->join('position', 'position.id', '=', 'person_position.position_id')
                ->where('person_id', $personId)
                ->orderBy('position.title')
                ->get();
     }
}

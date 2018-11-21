<?php

namespace App\Models;

use App\Models\Person;
use App\Models\ApiModel;
use App\Models\Position;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

     /**
      * Remove all positions from a person in response to status change, add back
      * the default roles if requested.
      *
      * @param integer $personId person id to change the roles
      * @param string $reason reason for reset
      * @param bool $resetToDefaultPositions if true, add the default positions
      */

      public static function resetPositions($personId, $reason, $action)
      {
          $removeIds = self::where('person_id', $personId)->pluck('position_id')->toArray();

          if ($action == Person::ADD_NEW_USER) {
              $addIds = [];
              $ids = Position::where('new_user_eligible', true)->pluck('id')->toArray();
              foreach ($ids as $positionId) {
                  if (in_array($positionId, $removeIds)) {
                      $removeIds = array_diff($removeIds, [ $positionId]);
                  } else {
                      $addIds[] = $positionId;
                  }
              }
              self::addIdsToPerson($personId, $addIds, $reason);
          }

          self::removeIdsFromPerson($personId, $removeIds, $reason);
      }

      /*
       * Add positions to a person. Log the action.
       *
       * @param integer $personid person to remove
       * @param array $ids position ids to remove
       * @param string $message reason for addition
       */

      public static function addIdsToPerson($personId, $ids, $message)
      {
          foreach ($ids as $id) {
            // Don't worry if there is a duplicate record.
            if (DB::affectingStatement("INSERT IGNORE INTO person_position SET person_id=?, position_id=?", [ $personId, $id]) == 1) {
                PersonPosition::log($personId, $id, 'add', $message);
            }
          }
      }


    /**
     * Remove positions from a person. Log the action.
     *
     * @param integer $personid person to remove
     * @param array $ids position ids to remove
     * @param string $message reason for removal
     */

     public static function removeIdsFromPerson($personId, $ids, $message)
     {
         if (empty($ids)) {
             return;
         }

         DB::table('person_position')
            ->where('person_id', $personId)
            ->whereIn('position_id', $ids)
            ->delete();

         foreach ($ids as $id) {
             PersonPosition::log($personId, $id, 'remove', $message);
         }
     }

     /**
      * Log changes to person_position
      *
      * @param integer $personId person idea
      * @param integer $id position id to log
      * @param string $action action taken - usually, 'add' or 'remove'
      * @param string $reason optional reason for action ('schedule add', 'trainer removed', etc.)
      */

     public static function log($personId, $id, $action, $reason=null)
     {
         ActionLog::record(Auth::user(), 'position-'.$action, $reason, [ 'position_id' => $id ], $personId);
     }
}

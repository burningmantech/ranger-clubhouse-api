<?php

namespace App\Models;

use App\Models\ApiModel;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\Person;

class PersonRole extends ApiModel
{

    protected $table = 'person_role';

    public function person() {
        return $this->belongsTo(Person::class);
    }

    public function role() {
        return $this->belongsTo(Role::class);
    }

    public static function findRolesForPerson($personId)
    {
        return PersonRole::select('role.id', 'role.title')
                ->where('person_id', $personId)
                ->join('role', 'role.id', '=', 'person_role.role_id')
                ->orderBy('role.title')->get();
    }

    public static function findRoleIdsForPerson($personId)
    {
        return PersonRole::where('person_id', $personId)->pluck('role_id')->toArray();
    }

    /**
     * Remove all roles from a person in response to status change, add back
     * the default roles if requested.
     *
     * @param integer $personId person id to change the roles
     * @param string $newStatus the new person status
     * @param bool $resetToDefaultRoles add back/keep the default roles
     */

     public static function resetRoles($personId, $reason, $action)
     {
         $removeIds = self::findRoleIdsForPerson($personId);

         if ($action == Person::ADD_NEW_USER) {
             $addIds = [];
             $ids = Role::where('new_user_eligible', true)->pluck('id')->toArray();
             foreach ($ids as $roleId) {
                 $key = array_search($roleId, $removeIds);
                 if ($key !== false) {
                     unset($removeIds[$key]);
                 } else {
                     $addIds[] = $roleId;
                 }
             }
             PersonRole::addIdsToPerson($personId, $addIds, $reason);
         }

         PersonRole::removeIdsFromPerson($personId, $removeIds, $reason);
     }

    /**
     * Remove roles from a person. Log the action.
     *
     * @param integer $personid person to remove
     * @param array $ids roles to remove
     * @param string $message reason for removal
     */

    public static function removeIdsFromPerson($personId, $ids, $message)
    {
        if (empty($ids)) {
            return;
        }

        DB::table('person_role')->where('person_id', $personId)->whereIn('role_id', $ids)->delete();
        ActionLog::record(Auth::user(), 'person-role-remove', $message, [ 'role_ids' => array_values($ids) ], $personId);
    }

     /*
      * Add roles to a person. Log the action.
      *
      * @param integer $personid person to remove
      * @param array $ids roles to remove
      * @param string $message reason for addition
      */

    public static function addIdsToPerson($personId, $ids, $message)
    {
        $addedIds = [];
        foreach ($ids as $id) {
            // Don't worry if there is a duplicate record.
            if (DB::affectingStatement("INSERT IGNORE INTO person_role SET person_id=?,role_id=?", [ $personId, $id ]) == 1) {
                $addedIds[] = $id;
            }
        }

        if (!empty($addedIds)) {
            ActionLog::record(Auth::user(), 'person-role-add', $message, [ 'role_ids' => array_values($ids) ], $personId);
        }
    }


    /*
    * Log changes to person_role
    *
    * @param integer $personId person idea
    * @param integer $id role id to log
    * @param string $action action taken - usually, 'add' or 'remove'
    * @param string $reason optional reason for action ('schedule add', 'trainer removed', etc.)
    */

    public static function log($personId, $id, $action, $reason=null)
    {
        ActionLog::record(Auth::user(), 'person-role-'.$action, $reason, [ 'role_id' => $id ], $personId);
    }
}

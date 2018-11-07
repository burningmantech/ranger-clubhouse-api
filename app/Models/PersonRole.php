<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonRole extends Model
{

    protected $table = 'person_role';

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
}

<?php

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @property int $role_id
 * @property int $position_id
 */

class PositionRole extends ApiModel
{
    use HasCompositePrimaryKey;

    protected $table = 'position_role';
    protected $primaryKey = ['position_id', 'role_id'];

    protected $increments = false;

    /**
     * Find all rows for the given position
     *
     * @param int $positionId
     * @return Collection
     */

    public static function findAllForPosition(int $positionId): Collection
    {
        return self::where('position_id', $positionId)->get();
    }

    /**
     * Find all the positions with granted roles for a person
     *
     * @param int $personId
     * @return Collection
     */

    public static function findRolesForPerson(int $personId): Collection
    {
        return DB::table('person_position')
            ->select('position.id', 'position.title', 'position_role.role_id')
            ->where('person_id', $personId)
            ->join('position_role', 'position_role.position_id', 'person_position.position_id')
            ->join('position', 'position_role.position_id', 'position.id')
            ->get();
    }

    /**
     * Add a role to a position
     *
     * @param int $positionId
     * @param int $roleId
     * @param ?string $reason
     */

    public static function add(int $positionId, int $roleId, ?string $reason) : void
    {
        if ($roleId == Role::ADMIN || $roleId == Role::TECH_NINJA) {
            // Nope, don't allow unchecked privilege escalation.
            return;
        }

        $data = ['position_id' => $positionId, 'role_id' => $roleId];
        if (self::insertOrIgnore($data) == 1) {
            ActionLog::record(Auth::user(), 'position-role-add', $reason, $data);
        }
    }

    /**
     * Remove a role from a position
     *
     * @param int $positionId
     * @param int $roleId
     * @param string|null $reason
     */

    public static function remove(int $positionId, int $roleId, ?string $reason) : void
    {
        $data = ['position_id' => $positionId, 'role_id' => $roleId];
        self::where($data)->delete();
        ActionLog::record(Auth::user(), 'position-role-remove', $reason, $data);
    }
}
<?php

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
     * @param Person $person
     * @return array
     */

    public static function findRolesForPerson(Person $person): array
    {
        $rows = DB::table('person_position')
            ->select('position.id', 'position.title', 'position_role.role_id', 'position.require_training_for_roles', 'position.training_position_id')
            ->where('person_id', $person->id)
            ->join('position_role', 'position_role.position_id', 'person_position.position_id')
            ->join('position', 'position_role.position_id', 'position.id')
            ->get();

        $positions = [];
        $year = current_year();
        foreach ($rows as $row) {
            $position = [
                'id' => $row->id,
                'title' => $row->title,
                'role_id' => $row->role_id,
            ];

            if ($row->require_training_for_roles) {
                $position['require_training_for_roles'] = true;
                $position['is_trained'] = Training::didPersonPassForYear($person, $row->training_position_id, $year);
            }
            $positions[] = $position;
        }

        return $positions;
    }

    /**
     * Add a role to a position
     *
     * @param int $positionId
     * @param int $roleId
     * @param ?string $reason
     */

    public
    static function add(int $positionId, int $roleId, ?string $reason): void
    {
        if ($roleId == Role::ADMIN || $roleId == Role::TECH_NINJA) {
            // Nope, don't allow unchecked privilege escalation.
            return;
        }

        $data = ['position_id' => $positionId, 'role_id' => $roleId];
        if (self::insertOrIgnore($data) == 1) {
            Cache::flush();
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

    public
    static function remove(int $positionId, int $roleId, ?string $reason): void
    {
        $data = ['position_id' => $positionId, 'role_id' => $roleId];
        self::where($data)->delete();
        Cache::flush();
        ActionLog::record(Auth::user(), 'position-role-remove', $reason, $data);
    }
}
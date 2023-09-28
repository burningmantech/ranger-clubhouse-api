<?php

namespace App\Models;

use App\Lib\ClubhouseCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * @property int person_id
 * @property int role_id
 */
class PersonRole extends ApiModel
{
    protected $table = 'person_role';

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Find all held roles for the given person
     *
     * @param int $personId
     * @return Collection
     */

    public static function findRolesForPerson(int $personId): Collection
    {
        return PersonRole::select('role.id', 'role.title')
            ->where('person_id', $personId)
            ->join('role', 'role.id', 'person_role.role_id')
            ->orderBy('role.title')
            ->get();
    }

    /**
     * Find all the role ids for the given person
     *
     * @param int $personId
     * @return array
     */

    public static function findRoleIdsForPerson(int $personId): array
    {
        return PersonRole::where('person_id', $personId)->pluck('role_id')->toArray();
    }

    /**
     * Remove all roles from a person in response to status change, add back
     * the default roles if requested.
     *
     * @param int $personId person id to change the roles
     * @param string|null $reason
     * @param int $action
     */

    public static function resetRoles(int $personId, ?string $reason, int $action): void
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
     * @param int $personId person to remove
     * @param mixed $ids roles to remove
     * @param ?string $message reason for removal
     */

    public static function removeIdsFromPerson(int $personId, mixed $ids, ?string $message = null): void
    {
        if (empty($ids)) {
            return;
        }

        DB::table('person_role')->where('person_id', $personId)->whereIn('role_id', $ids)->delete();
        ActionLog::record(Auth::user(), 'person-role-remove', $message, ['role_ids' => array_values($ids)], $personId);
        self::clearCache($personId);
    }

    /**
     * Remove all roles from a person.
     *
     * @param int $personId
     * @param string|null $message
     * @return void
     */

    public static function removeAllFromPerson(int $personId, ?string $message = null): void
    {
        $ids = DB::table('person_role')->where('person_id', $personId)->pluck('role_id')->toArray();
        self::removeIdsFromPerson($personId, $ids, $message);
    }


    /**
     * Add roles to a person. Log the action.
     *
     * @param int $personId person to remove
     * @param array $ids roles to remove
     * @param ?string $message reason for addition
     */

    public static function addIdsToPerson(int $personId, mixed $ids, ?string $message): void
    {
        $addedIds = [];
        foreach ($ids as $id) {
            // Don't worry if there is a duplicate record.
            if (DB::table('person_role')->insertOrIgnore([ 'person_id' => $personId, 'role_id' => $id]) == 1) {
                $addedIds[] = $id;
            }
        }

        if (!empty($addedIds)) {
            ActionLog::record(Auth::user(), 'person-role-add', $message, ['role_ids' => array_values($ids)], $personId);
            self::clearCache($personId);
        }
    }

    /**
     * Log changes to person_role
     *
     * @param int $personId person idea
     * @param int $id role id to log
     * @param string $action action taken - usually, 'add' or 'remove'
     * @param ?string $reason optional reason for action ('schedule add', 'trainer removed', etc.)
     */

    public static function log(int $personId, int $id, string $action, ?string $reason = null): void
    {
        ActionLog::record(Auth::user(), 'person-role-' . $action, $reason, ['role_id' => $id], $personId);
    }

    /**
     * Clear the roles cache for the given person.
     *
     * @param int $personId
     * @return void
     */

    public static function clearCache(int $personId): void
    {
        ClubhouseCache::forget(self::cacheKey($personId));
    }

    /**
     * Get the cache key for the given person
     * @param int $personId
     * @return string
     */

    public static function cacheKey(int $personId): string
    {
        return 'person-role-' . $personId;
    }

    /**
     * Obtain the cache roles for a person
     *
     * @param int $personId
     * @return mixed
     * @throws InvalidArgumentException
     */

    public static function getCache(int $personId): mixed
    {
        return ClubhouseCache::get(self::cacheKey($personId));
    }

    /**
     * Put the cached roles for a person
     *
     * @param int $personId
     * @param mixed $roles
     */

    public static function putCache(int $personId, mixed $roles): void
    {
        ClubhouseCache::put(self::cacheKey($personId), $roles);
    }
}

<?php

namespace App\Models;

use App\Lib\AwardManagement;
use App\Traits\HasCompositePrimaryKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @property int $person_id
 * @property int $team_id
 *
 */
class PersonTeam extends ApiModel
{
    use HasFactory;
    use HasCompositePrimaryKey;

    protected $table = 'person_team';
    protected $primaryKey = ['person_id', 'team_id'];

    protected $guarded = [];

    public static function boot(): void
    {
        parent::boot();

        self::saved(function ($model) {
            AwardManagement::rebuildForPersonId($model->person_id);
        });

        self::deleted(function ($model) {
            AwardManagement::rebuildForPersonId($model->person_id);
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Find all team memberships for the person
     *
     * @param int $personId
     * @return Collection
     */

    public static function findAllTeamsForPerson(int $personId): Collection
    {
        return Team::select(
            'team.*',
            DB::raw('EXISTS (SELECT 1 FROM position WHERE team.id=position.team_id LIMIT 1) as has_positions')
        )->join('person_team', 'team.id', 'person_team.team_id')
            ->where('person_team.person_id', $personId)
            ->orderBy('team.title')
            ->get();
    }

    /**
     * Retrieve all Cadre/Delegation membership team ids for a given person.
     *
     * @param int $personId
     * @return array
     */

    public static function retrieveCadreMembershipIds(int $personId): array
    {
        return DB::table('person_team')
            ->select('team.id')
            ->join('team', 'person_team.team_id', '=', 'team.id')
            ->where('team.active', true)
            ->where('person_team.person_id', $personId)
            ->whereIn('team.type', [Team::TYPE_CADRE, Team::TYPE_DELEGATION])
            ->get()
            ->pluck('id')
            ->toArray();
    }

    /**
     * Find all teams with mvr eligible for the person
     *
     * @param int $personId
     * @return array
     */

    public static function retrieveMVREligibleForPerson(int $personId): array
    {
        return DB::table('team')
            ->select('team.id', 'team.title')
            ->join('person_team', 'person_team.team_id', 'team.id')
            ->where('person_team.person_id', $personId)
            ->where('team.active', true)
            ->where('team.mvr_eligible', true)
            ->orderBy('team.title')
            ->get()
            ->toArray();
    }

    /**
     * Find all teams with pvr eligible for the person
     *
     * @param int $personId
     * @return array
     */

    public static function retrievePVREligibleForPerson(int $personId): array
    {
        return DB::table('team')
            ->select('team.id', 'team.title')
            ->join('person_team', 'person_team.team_id', 'team.id')
            ->where('person_team.person_id', $personId)
            ->where('team.active', true)
            ->where('team.pvr_eligible', true)
            ->orderBy('team.title')
            ->get()
            ->toArray();
    }


    /**
     * Is the person a member of a MVR eligible team?
     *
     * @param int $personId
     * @return bool
     */

    public static function haveMVREligibleForPerson(int $personId): bool
    {
        return DB::table('team')
            ->join('person_team', 'person_team.team_id', 'team.id')
            ->where('person_team.person_id', $personId)
            ->where('team.active', true)
            ->where('team.mvr_eligible', true)
            ->limit(1)
            ->exists();
    }

    /**
     * Is the person a member of a PVR eligible team?
     *
     * @param int $personId
     * @return bool
     */

    public static function havePVREligibleTeam(int $personId): bool
    {
        return DB::table('team')
            ->join('person_team', 'person_team.team_id', 'team.id')
            ->where('person_team.person_id', $personId)
            ->where('team.active', true)
            ->where('team.pvr_eligible', true)
            ->limit(1)
            ->exists();
    }

    /**
     * Is the person a team member?
     *
     * @param int $teamId
     * @param int $personId
     * @return bool
     */

    public static function haveTeam(int $teamId, int $personId): bool
    {
        return self::where('team_id', $teamId)->where('person_id', $personId)->exists();
    }

    /**
     * Find a membership record for the person and team.
     *
     * @param int $teamId
     * @param int $personId
     * @return PersonTeam|null
     */

    public static function findForPerson(int $teamId, int $personId): PersonTeam|null
    {
        return self::where('person_id', $personId)->where('team_id', $teamId)->first();
    }

    /**
     * Add a person to a team. Log the addition.
     *
     * @param int $teamId
     * @param int $personId
     * @param string|null $reason
     * @return void
     * @throws AuthorizationException
     */

    public static function addPerson(int $teamId, int $personId, ?string $reason): void
    {
        $hasTechNinja = DB::table('team_role')->where(['team_id' => $teamId, 'role_id' => Role::TECH_NINJA])->exists();
        if ($hasTechNinja && !Auth::user()?->hasRole(Role::TECH_NINJA)) {
            throw new AuthorizationException("No authorization to grant membership for a team with the Tech Ninja permission associated.");
        }

        $hasAdmin = DB::table('team_role')->where(['team_id' => $teamId, 'role_id' => Role::ADMIN])->exists();
        if ($hasAdmin && !Auth::user()?->isAdmin()) {
            throw new AuthorizationException("No authorization to grant membership for a team with the Admin permission associated.");
        }

        self::create(['team_id' => $teamId, 'person_id' => $personId]);
        PersonTeamLog::addPerson($teamId, $personId);
        ActionLog::record(Auth::user(), 'person-team-add', $reason, ['team_id' => $teamId], $personId);
    }

    /**
     * Remove a person from a team.
     *
     * @param $teamId
     * @param $personId
     * @param $reason
     * @return void
     */

    public static function removePerson($teamId, $personId, $reason): void
    {
        self::where(['team_id' => $teamId, 'person_id' => $personId])->delete();
        PersonTeamLog::removePerson($teamId, $personId);
        ActionLog::record(Auth::user(), 'person-team-remove', $reason, ['team_id' => $teamId], $personId);
    }

    /**
     * Remove all teams for a person
     *
     * @param int $personId
     * @param string $reason
     * @return void
     */

    public static function removeAllFromPerson(int $personId, string $reason): void
    {
        $teams = self::findAllTeamsForPerson($personId);

        foreach ($teams as $team) {
            self::removePerson($team->id, $personId, $reason);
        }
    }
}

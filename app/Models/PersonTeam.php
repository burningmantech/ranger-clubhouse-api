<?php

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

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
        return Team::select('team.*')
            ->join('person_team', 'team.id', 'person_team.team_id')
            ->where('person_team.person_id', $personId)
            ->orderBy('team.title')
            ->get();
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
     */

    public static function addPerson(int $teamId, int $personId, ?string $reason): void
    {
        if (self::insertOrIgnore(['team_id' => $teamId, 'person_id' => $personId]) == 1) {
            PersonTeamLog::addPerson($teamId, $personId);
            ActionLog::record(Auth::user(), 'person-team-add', $reason, ['team_id' => $teamId], $personId);
        }
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
        if (self::where(['team_id' => $teamId, 'person_id' => $personId])->delete()) {
            PersonTeamLog::removePerson($teamId, $personId);
            ActionLog::record(Auth::user(), 'person-team-remove', $reason, ['team_id' => $teamId], $personId);
        }
    }

    /**
     * Remove all teams for a person
     *
     * @param int $personId
     * @param string $reason
     * @return void
     */

    public static function removeAllForPerson(int $personId, string $reason) : void
    {
        $teams = self::findAllTeamsForPerson($personId);

        foreach ($teams as $team) {
            self::removePerson($team->id, $personId, $reason);
        }
    }
}

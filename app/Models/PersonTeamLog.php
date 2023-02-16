<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $person_id
 * @property int $team_id
 * @property Carbon|string|null $left_on
 * @property Carbon|string|null $joined_on
 */
class PersonTeamLog extends ApiModel
{
    protected $table = 'person_team_log';
    protected $auditModel = true;
    public $timestamps = true;

    protected $guarded = [
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'joined_on' => 'date:Y-m-d',
        'left_on' => 'date:Y-m-d',
    ];

    protected $rules = [
        'person_id' => 'required|exists:person,id',
        'team_id' => 'required|exists:team,id',
        'joined_on' => 'required',
        'left_on' => 'nullable|sometimes|after_or_equal:joined_on'
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public static function findForQuery($query)
    {
        $personId = $query['person_id'] ?? null;
        $teamId = $query['team_id'] ?? null;

        $sql = self::query();

        if ($personId) {
            $sql->where('person_id', $personId);
        } else {
            $sql->with('person:id,callsign');
        }

        if ($teamId) {
            $sql->where('team_id', $teamId);
        } else {
            $sql->with('team:id,title,type');
        }

        return $sql->get()->sortBy($teamId ? 'joined_on' : ['team.title', 'joined_on'])->values();
    }

    public function loadRelationships()
    {
        $this->load(['person:id,callsign', 'team:id,title,type']);
    }

    /**
     * Record a person joining a team
     *
     * @param int $teamId
     * @param int $personId
     */

    public static function addPerson(int $teamId, int $personId)
    {
        self::insert(['person_id' => $personId, 'team_id' => $teamId, 'joined_on' => now()]);
    }

    /**
     * Record a person leaving a team.
     *
     * @param int $teamId
     * @param int $personId
     */

    public static function removePerson(int $teamId, int $personId)
    {
        self::where(['person_id' => $personId, 'team_id' => $teamId])->update(['left_on' => now()]);
    }

    public function setLeftOnAttribute($date)
    {
        $this->attributes['left_on'] = empty($date) ? null : Carbon::parse($date);
    }
}


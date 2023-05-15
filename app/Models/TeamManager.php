<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class TeamManager extends ApiModel
{
    use HasFactory;

    protected $table = 'team_manager';
    public $timestamps = true;
    public bool $auditModel = true;

    // Model is not publicly accessible
    protected $guarded = [];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Find all team manager records for a person
     *
     * @param int $personId
     * @return Collection
     */

    public static function findForPerson(int $personId): Collection
    {
        return self::where('person_id', $personId)->get();
    }

    /**
     * Find a team for a given person
     *
     * @param int $teamId
     * @param int $personId
     * @return static|null
     */

    public static function findTeamForPerson(int $teamId, int $personId): ?self
    {
        return self::where('team_id', $teamId)->where('person_id', $personId)->first();
    }

    /**
     * Retrieve the teams a person if a manager.
     *
     * @param int $personId
     * @return array
     */

    public static function retrieveTeamsForPerson(int $personId): array
    {
        return self::where('person_id', $personId)
            ->with('team')
            ->get()
            ->map(fn($r) => [
                'id' => $r->team_id,
                'title' => $r->team->title
            ])->toArray();
    }

    /**
     * Retrieve the team ids for a person
     *
     * @param int $personId
     * @return array<int>
     */

    public static function retrieveTeamIdsForPerson(int $personId): array
    {
        return DB::table('team_manager')->where('person_id', $personId)->pluck('team_id')->toArray();
    }

    /**
     * Add a team manager
     *
     * @param int $teamId
     * @param int $personId
     * @param string|null $reason
     * @return void
     */

    public static function addPerson(int $teamId, int $personId, ?string $reason): void
    {
        $query = ['team_id' => $teamId, 'person_id' => $personId];
        if (DB::table('team_manager')->where($query)->exists()) {
            return;
        }

        $tm = new self($query);
        $tm->auditReason = $reason;
        $tm->save();
    }

    /**
     * Remove a team manager
     *
     * @param $teamId
     * @param $personId
     * @param $reason
     * @return void
     */

    public static function removePerson($teamId, $personId, $reason): void
    {
        $tm = self::where(['team_id' => $teamId, 'person_id' => $personId])->first();
        if (!$tm) {
            return;
        }

        $tm->auditReason = $reason;
        $tm->delete();
    }
}

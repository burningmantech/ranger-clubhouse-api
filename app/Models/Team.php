<?php

namespace App\Models;

use App\Attributes\NullIfEmptyAttribute;
use App\Lib\ClubhouseCache;
use App\Lib\Membership;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @property boolean $active
 * @property string $title
 * @property string $type
 * @property ?array $role_ids
 */
class Team extends ApiModel
{
    use HasFactory;

    protected $table = 'team';
    public $timestamps = true;
    public bool $auditModel = true;

    const string TYPE_TEAM = 'team';
    const string TYPE_CADRE = 'cadre';
    const string TYPE_DELEGATION = 'delegation';

    protected $fillable = [
        'active',
        'awards_eligible',
        'description',
        'email',
        'mvr_eligible',
        'pvr_eligible',
        'title',
        'type',
        'role_ids'
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'awards_eligible' => 'boolean',
            'mvr_eligible' => 'boolean',
            'pvr_eligible' => 'boolean',
        ];
    }

    protected $rules = [
        'title' => 'required|string|max:100',
        'role_ids' => 'sometimes|array',
        'role_ids.*' => 'sometimes|integer'
    ];

    protected $hidden = [
        'pivot',
        'team_roles'
    ];

    public array|null $role_ids = null;

    const string DELETE_REASON = 'Team deletion';

    public static function boot(): void
    {
        parent::boot();

        self::saving(function (Team $model) {
            if (!$model->awards_eligible) {
                // Ensure the automation does not trigger
                $model->awards_grants_service_year = false;
                $model->awards_auto_grant = false;
            }
        });

        self::saved(function ($model) {
            // Don't use empty() because the array might be empty indicating no roles are to be assigned
            if (is_array($model->role_ids) && Auth::user()?->hasRole(Role::TECH_NINJA)) {
                // Update Position Roles
                Membership::updateTeamRoles($model->id, $model->role_ids, $model->auditReason ?? '');
            }
            ClubhouseCache::flush();
        });

        self::deleted(function ($model) {
            $awardId = $model->id;
            PersonAward::where('id', $awardId)->deleteWithReason(self::DELETE_REASON);
            PersonTeam::where('team_id', $awardId)->deleteWithReason(self::DELETE_REASON);
            PersonTeamLog::where('team_id', $awardId)->deleteWithReason(self::DELETE_REASON);
            Position::where('team_id', $awardId)->update(['team_id' => null]);
            TeamRole::where('team_id', $awardId)->deleteWithReason(self::DELETE_REASON);
            ClubhouseCache::flush();
        });
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function members(): HasManyThrough
    {
        return $this->hasManyThrough(Person::class, PersonTeam::class, 'team_id', 'id', 'id', 'person_id')
            ->orderBy('person.callsign');
    }

    public function team_roles(): HasMany
    {
        return $this->hasMany(TeamRole::class, 'team_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'team_role');
    }

    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'team_manager')
            ->select('person.id', 'person.callsign', 'person.status')
            ->orderBy('person.callsign');
    }

    /**
     * Find all Team records
     *
     * @param bool $onlyActive
     * @return Collection
     */

    public static function findAll(bool $onlyActive = false): Collection
    {
        $sql = self::orderBy('title');

        if ($onlyActive) {
            $sql->where('active', true);
        }

        return $sql->get();
    }

    /**
     * Find a team by its title.
     *
     * @param string $title
     * @return Team|null
     */

    public static function findByTitle(string $title): Team|null
    {
        return self::where('title', $title)->first();
    }

    /**
     * @param array $query
     * @param ?int $personId
     * @param bool $isAdmin
     * @return Collection
     */

    public static function findForQuery(array $query, ?int $personId = null, bool $isAdmin = false): Collection
    {
        $canManage = $query['can_manage'] ?? false;
        $includeRoles = $query['include_roles'] ?? false;
        $includeManagers = $query['include_managers'] ?? false;
        $awardsEligible = $query['awards_eligible'] ?? false;

        $sql = self::query()->select('team.*')->orderBy('team.title');

        if ($canManage && !$isAdmin) {
            $sql->leftJoin('team_manager', function ($j) use ($personId) {
                $j->on('team_manager.team_id', 'team.id');
                $j->where('team_manager.person_id', $personId);
            })->addSelect(DB::raw('IF(team_manager.team_id is null, false, true) AS can_manage'));
        }

        if ($awardsEligible) {
            $sql->where('awards_eligible', true);
        }

        if ($includeManagers) {
            $sql->with('managers');
        }

        $rows = $sql->get();

        if ($canManage && $isAdmin) {
            foreach ($rows as $row) {
                $row->can_manage = true;
            }
        }

        if ($includeRoles) {
            $rows->load('team_roles');
            foreach ($rows as $row) {
                $row->loadRoles();
            }
        }

        if ($includeManagers) {
            foreach ($rows as $row) {
                $row->makeVisible('managers');
            }
        }

        return $rows;
    }

    /**
     * Load the roles associated with the team, and set the pseudo column role_ids
     * @return void
     */

    public function loadRoles(): void
    {
        $this->role_ids = $this->team_roles->pluck('role_id')->toArray();
        $this->append('role_ids');
    }

    /**
     * Get the pseudo column role_ids
     *
     * @return array|null
     */

    public function getRoleIdsAttribute(): ?array
    {
        return $this->role_ids;
    }

    public function isAwardsEligible(): bool
    {
        return in_array($this->type, [self::TYPE_CADRE, self::TYPE_DELEGATION]) || $this->awards_eligible;
    }

    /**
     * Set the pseudo column role_ids
     *
     * @param $value
     * @return void
     */

    public function setRoleIdsAttribute($value): void
    {
        $this->role_ids = $value;
    }

    public function email(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function description(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }
}

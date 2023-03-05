<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Role extends ApiModel
{
    protected $table = 'role';
    protected $auditModel = true;

    const ADMIN = 1;   // Super user! Change anything
    const VIEW_PII = 2;   // See email, address, phone
    const VIEW_EMAIL = 3;   // See email
    const GRANT_POSITION = 4;   // Obsoleted: Grand/Revoke Positions (superseded by Clubhouse Teams & role assoc.)
    const EDIT_ACCESS_DOCS = 5;   // Edit Access Documents
    const EDIT_BMIDS = 6;   // Edit BMIDs
    const EDIT_SLOTS = 7;   // Edit Slots
    const LOGIN = 11;  // Obsoleted: Person allowed to login (not used, superseded by the suspend status)
    const MANAGE = 12;  // Ranger HQ: access other schedule, asset checkin/out, send messages
    const INTAKE = 13;  // Intake Management
    const MENTOR = 101; // Mentor - access mentor section
    const TRAINER = 102; // Trainer
    const VC = 103; // Volunteer Coordinator -
    const ART_TRAINER = 104; // ART trainer
    const MEGAPHONE = 105; // RBS access
    const TIMESHEET_MANAGEMENT = 106; // Create, edit, correct, verify timesheets
    const SURVEY_MANAGEMENT = 107; // Allow to create/edit/delete surveys, and view responders identity.
    const MANAGE_ON_PLAYA = 108; // Treated as MANAGE if setting LoginManageOnPlayaEnabled is true
    const TRAINER_SEASONAL = 109; // Treated as TRAINER if setting TrainingSeasonalRoleEnabled is true
    const CERTIFICATION_MGMT = 110; // Person can add certifications on a person's behalf, and view detailed info (card number, notes, etc.)
    const EDIT_ASSETS = 111;    // Person can create and edit asset records
    const EDIT_SWAG = 112;      // Person can create and edit swag records

    const TECH_NINJA = 1000;    // godlike powers granted - access to dangerous maintenance functions, raw database access.

    protected $casts = [
        'new_user_eligible' => 'bool'
    ];

    protected $rules = [
        'title' => 'required'
    ];

    protected $fillable = [
        'title',
        'new_user_eligible'
    ];

    public function person_role(): HasMany
    {
        return $this->hasMany(PersonRole::class);
    }

    public function teams(): HasManyThrough
    {
        return $this->hasManyThrough(
            Team::class,
            TeamRole::class, 'role_id', 'id', 'id', 'team_id'
        )->orderBy('team.title');
    }

    public function positions(): HasManyThrough
    {
        return $this->hasManyThrough(
            Position::class,
            PositionRole::class, 'role_id', 'id', 'id', 'position_id'
        )->orderBy('position.title');
    }

    /**
     * Find all the roles and any associated teams and/or positions
     *
     * @param array $query <string,mixed>
     * @return Collection
     */

    public static function findForQuery(array $query): Collection
    {
        $sql = self::orderBy('title');
        if ($query['include_associations'] ?? false) {
            $sql->with('teams:id,title', 'positions:id,title');
        }
        return $sql->get();
    }
}

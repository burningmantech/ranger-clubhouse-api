<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Role extends ApiModel
{
    protected $table = 'role';
    protected bool $auditModel = true;

    const int ADMIN = 1;   // Super user! Change anything
    const int VIEW_PII = 2;   // See email, address, phone
    const int VIEW_EMAIL = 3;   // See email
    const int GRANT_POSITION = 4;   // Obsoleted: Grand/Revoke Positions (superseded by Clubhouse Teams & role assoc.)
    const int EDIT_ACCESS_DOCS = 5;   // Edit Access Documents
    const int EDIT_BMIDS = 6;   // Edit BMIDs
    const int EDIT_SLOTS = 7;   // Edit Slots
    const int LOGIN = 11;  // Obsoleted: Person allowed to login (not used, superseded by the suspend status)
    const int MANAGE = 12;  // Ranger HQ: access other schedule, asset checkin/out, send messages
    const int INTAKE = 13;  // Intake Management
    const int MENTOR = 101; // Mentor - access mentor section
    const int TRAINER = 102; // Trainer
    const int VC = 103; // Volunteer Coordinator -
    const int ART_TRAINER = 104; // ART trainer
    const int MEGAPHONE = 105; // RBS access
    const int TIMESHEET_MANAGEMENT = 106; // Create, edit, correct, verify timesheets
    const int SURVEY_MANAGEMENT = 107; // Allow to create/edit/delete surveys, and view responders identity.
    const int MANAGE_ON_PLAYA = 108; // Treated as MANAGE if setting LoginManageOnPlayaEnabled is true
    const int TRAINER_SEASONAL = 109; // Treated as TRAINER if setting TrainingSeasonalRoleEnabled is true
    const int CERTIFICATION_MGMT = 110; // Person can add certifications on a person's behalf, and view detailed info (card number, notes, etc.)
    const int EDIT_ASSETS = 111;    // Person can create and edit asset records
    const int EDIT_SWAG = 112;      // Person can create and edit swag records
    const int CAN_FORCE_SHIFT = 113; // Person can force a shift start

    const int REGIONAL_MANAGEMENT = 114;    // Person can access Regional Ranger liaison features.
    const int PAYROLL = 115;                // Can access payroll features
    const int VEHICLE_MANAGEMENT = 116;     // Can access vehicle fleet management features
    const int TIMECARD_YEAR_ROUND = 117;    // Paid folks who can self check in/out, and submit timesheet corrections year round.
    const int SALESFORCE_IMPORT = 118;      // Allowed to import new accounts from Salesforce
    const int MESSAGE_MANAGEMENT = 119;     // Allow access to Clubhouse Messages year round regardless of LMOP Enabled setting.
    const int EDIT_CLOTHING = 120;   // Can edit a clothing fields.
    const int MEGAPHONE_TEAM_ONPLAYA = 121;  // On-Playa Megaphone permission
    const int MEGAPHONE_EMERGENCY_ONPLAYA = 122;    // Allows access to the broadcast all emergency
    const int ANNOUNCEMENT_MANAGEMENT = 123;    // Allow announcements to be created and deleted.

    const int TECH_NINJA = 1000;    // godlike powers granted - access to dangerous maintenance functions, raw database access.


    protected function casts(): array
    {
        return [
            'new_user_eligible' => 'bool'
        ];
    }

    protected $rules = [
        'title' => 'required'
    ];

    protected $fillable = [
        'title',
        'new_user_eligible'
    ];

    public static function boot(): void
    {
        parent::boot();

        self::deleted(function ($model) {
            PersonRole::where('role_id', $model->id)->delete();
        });
    }

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

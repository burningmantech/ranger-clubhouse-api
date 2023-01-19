<?php

namespace App\Models;

class Role extends ApiModel
{
    protected $table = 'role';
    protected $auditModel = true;

    const ADMIN            = 1;   // Super user! Change anything
    const VIEW_PII         = 2;   // See email, address, phone
    const VIEW_EMAIL       = 3;   // See email
    const GRANT_POSITION   = 4;   // Grand/Revoke Positions (superseded by Clubhouse Teams)
    const EDIT_ACCESS_DOCS = 5;   // Edit Access Documents
    const EDIT_BMIDS       = 6;   // Edit BMIDs
    const EDIT_SLOTS       = 7;   // Edit Slots
    const LOGIN            = 11;  // Person allowed to login (not used, superseded by the suspend status)
    const MANAGE           = 12;  // Ranger HQ: access other schedule, asset checkin/out, send messages
    const INTAKE           = 13;  // Intake Management
    const MENTOR           = 101; // Mentor - access mentor section
    const TRAINER          = 102; // Trainer
    const VC               = 103; // Volunteer Coordinator -
    const ART_TRAINER      = 104; // ART trainer
    const MEGAPHONE        = 105; // RBS access
    const TIMESHEET_MANAGEMENT = 106; // Create, edit, correct, verify timesheets
    const SURVEY_MANAGEMENT = 107; // Allow to create/edit/delete surveys, and view responders identity.
    const MANAGE_ON_PLAYA = 108; // Treated as MANAGE if setting LoginManageOnPlayaEnabled is true
    const TRAINER_SEASONAL = 109; // Treated as TRAINER if setting TrainingSeasonalRoleEnabled is true
    const CERTIFICATION_MGMT = 110; // Person can add certifications on a person's behalf, and view detailed info (card number, notes, etc.)

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

    public function person_role() {
        return $this->hasMany(PersonRole::class);
    }

    public static function findAll()
    {
        return self::orderBy('title')->get();
    }
}

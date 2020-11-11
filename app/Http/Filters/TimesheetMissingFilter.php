<?php

namespace App\Http\Filters;

use App\Models\Person;
use App\Models\Role;
use App\Models\TimesheetMissing;

class TimesheetMissingFilter
{
    protected $record;

    public function __construct(TimesheetMissing $record)
    {
        $this->record = $record;
    }

    const USER_FIELDS = [
        'additional_notes',
        'off_duty',
        'on_duty',
        'partner',
        'person_id',
        'position_id',
    ];

    const MANAGE_FIELDS = [
        'review_status',
        'additional_reviewer_notes',
        'create_entry',
        'new_off_duty',
        'new_on_duty',
        'new_position_id',
    ];

    public function deserialize(Person $user = null): array
    {
        if ($user->hasRole([ Role::ADMIN, Role::TIMESHEET_MANAGEMENT ]))
            return array_merge(self::USER_FIELDS, self::MANAGE_FIELDS);

        return self::USER_FIELDS;
    }
}

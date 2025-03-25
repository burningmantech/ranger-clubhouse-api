<?php

namespace App\Http\Filters;

use App\Models\Person;
use App\Models\Role;
use App\Models\TimesheetMissing;

class TimesheetMissingFilter
{
    public function __construct(public TimesheetMissing $record)
    {
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
        'create_entry',
        'new_off_duty',
        'new_on_duty',
        'new_position_id',
        'review_status',
    ];

    const WRANGLER_FIELDS = [
        'additional_admin_notes',
        'additional_wrangler_notes',
    ];


    public function deserialize(Person $user = null): array
    {
        $fields = [self::USER_FIELDS];

        if ($user->hasRole(Role::EVENT_MANAGEMENT)) {
            $fields[] = self::MANAGE_FIELDS;
        }

        if ($user->hasRole([Role::ADMIN, Role::TIMESHEET_MANAGEMENT])) {
            $fields[] = self::WRANGLER_FIELDS;
        }

        return array_merge(...$fields);
    }
}

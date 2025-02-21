<?php

namespace App\Http\Filters;

use App\Models\Person;
use App\Models\Role;
use App\Models\Timesheet;

class TimesheetFilter
{
    public function __construct(public Timesheet $record)
    {
    }

    const array USER_FIELDS = [
        'additional_notes',
        'desired_position_id',
        'desired_on_duty',
        'desired_off_duty',
        'review_status'
    ];

    const array MANAGE_FIELDS = [
        'additional_worker_notes',
        'off_duty',
        'on_duty',
    ];

    const array WRANGLER_FIELDS = [
        'additional_admin_notes',
        'additional_wrangler_notes',
        'is_non_ranger',
        'person_id',
        'position_id',
        'suppress_duration_warning'
    ];

    public function deserialize(?Person $user = null): array
    {
        $fields = [self::USER_FIELDS];

        if ($user->hasRole([Role::ADMIN, Role::SHIFT_MANAGEMENT])) {
            $fields[] = self::MANAGE_FIELDS;
        }

        if ($user->hasRole([Role::ADMIN, Role::TIMESHEET_MANAGEMENT])) {
            $fields[] = self::WRANGLER_FIELDS;
        }

        return array_merge(...$fields);
    }
}

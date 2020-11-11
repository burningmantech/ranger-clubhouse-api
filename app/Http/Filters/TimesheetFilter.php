<?php

namespace App\Http\Filters;

use App\Models\Person;
use App\Models\Role;
use App\Models\Timesheet;

class TimesheetFilter
{
    protected $record;

    public function __construct(Timesheet $record)
    {
        $this->record = $record;
    }

    const USER_FIELDS = [
        'additional_notes',
        'review_status',
    ];

    const MANAGE_FIELDS = [
        'off_duty',
        'on_duty',
        'person_id',
        'position_id',
        'additional_reviewer_notes',
    ];

    public function deserialize(Person $user = null): array
    {
        if ($user->hasRole([Role::ADMIN, Role::TIMESHEET_MANAGEMENT]))
            return array_merge(self::USER_FIELDS, self::MANAGE_FIELDS);

        if ($this->record->person_id == $user->id || $user->hasRole(Role::MANAGE)) {
            return self::USER_FIELDS;
        }

        return []; // Should never be hit.
    }
}

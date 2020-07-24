<?php

namespace App\Http\Filters;

use App\Models\Person;
use App\Models\Vehicle;
use App\Models\Role;

/*
 * THE COLUMNS LISTED NEED TO BE IN SYNC WITH app/Models/PersonEvent.php::$fillable
 */

class VehicleFilter
{
    const REVIEW_FIELDS = [
        'status',
        'sticker_number',
    ];

    const ADMIN_FIELDS = [
        'notes'
    ];

    const VEHICLE_FIELDS = [
        'type',
        'person',       // eager loaded table
        'callsign',
        'person_id',
        'event_year',
        'team_assignment',
        'vehicle_class',
        'vehicle_year',
        'vehicle_make',
        'vehicle_model',
        'vehicle_color',
        'vehicle_type',
        'rental_number',
        'license_state',
        'license_number',
        'driving_sticker',
        'fuel_chit',
        'ranger_logo',
        'amber_light',
        'response',
        'request_comment'
    ];

    const VEHICLE_MAINTENANCE_FIELDS = [
        'driving_sticker'
    ];

    // Pseudo fields, read only - pulled from person_event
    const PAPERWORK_FIELDS = [
        'org_vehicle_insurance',
        'signed_motorpool_agreement'
    ];

    //
    // FIELDS_SERIALIZE & FIELDS_DESERIALIZE elements are
    // 0: array of field names
    // 1: allow fields if the person is the authorized user
    // 2: which roles are allowed the field (if null, allow any)
    //

    const FIELDS_SERIALIZE = [
        [self::VEHICLE_FIELDS],
        [self::REVIEW_FIELDS],
        [self::PAPERWORK_FIELDS],
        [self::ADMIN_FIELDS, false, [Role::ADMIN]],
        [self::VEHICLE_MAINTENANCE_FIELDS, true, [Role::ADMIN, Role::MANAGE]],
    ];

    const FIELDS_DESERIALIZE = [
        [self::VEHICLE_FIELDS, true, [Role::ADMIN]],
        [self::REVIEW_FIELDS, false, [Role::ADMIN]],
        [self::ADMIN_FIELDS, false, [Role::ADMIN]],
        [self::VEHICLE_MAINTENANCE_FIELDS, false, [Role::ADMIN, Role::MANAGE]],
    ];

    protected $record;

    public function __construct(Vehicle $record)
    {
        $this->record = $record;
    }

    public function serialize(Person $authorizedUser = null): array
    {
        return $this->buildFields(self::FIELDS_SERIALIZE, $authorizedUser);
    }

    public function buildFields(array $fieldGroups, $authorizedUser): array
    {
        $fields = [];

        if ($authorizedUser) {
            if ($this->record->id) {
                // Existing record..
                $isUser = ($this->record->person_id == $authorizedUser->id);
            } else {
                // Creating a new record
                $isUser = true;
            }
        } else {
            $isUser = false;
        }

        foreach ($fieldGroups as $group) {
            $roles = null;

            if (count($group) == 1) {
                $allow = true;
            } else {
                if ($isUser && $group[1] == true) {
                    $allow = true;
                } else {
                    $allow = false;
                }
                if (isset($group[2])) {
                    $roles = $group[2];
                }
            }

            if ($allow || ($authorizedUser && $roles && $authorizedUser->hasRole($roles))) {
                $fields = array_merge($fields, $group[0]);
            }
        }

        return $fields;
    }

    public function deserialize(Person $authorizedUser = null): array
    {
        return $this->buildFields(self::FIELDS_DESERIALIZE, $authorizedUser);
    }
}

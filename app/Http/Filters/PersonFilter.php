<?php

namespace App\Http\Filters;

use App\Models\Person;
use App\Models\Role;

/*
 * THE COLUMNS LISTED NEED TO BE IN SYNC WITH app/Models/Person.php::$fillable
 */

class PersonFilter
{
    protected $record;

    public function __construct(Person $record)
    {
        $this->record = $record;
    }

    const STATUS_FIELDS = [
        'status',
        'status_date',
    ];

    const ACCOUNT_FIELDS = [
        'vintage',
        'date_verified',
        'create_date',
        'timestamp',
        'sfuid',
        'logged_in_at',
        'last_seen_at'
    ];

    const ROLES_FIELDS = [
        'roles',
    ];

    const CERTIFICATIONS_FIELDS = [
        'osha10',
        'osha30',
    ];

    const NAME_GENDER_FIELDS = [
        'first_name',
        'mi',
        'last_name',
        'gender',
        'callsign_pronounce'
    ];

    const CALLSIGNS_FIELDS = [
        'callsign',
        'callsign_approved',
        'formerly_known_as',
    ];

    const EMAIL_FIELDS = [
        'email',
    ];

    const PERSONAL_INFO_FIELDS = [
        'street1',
        'street2',
        'apt',
        'city',
        'state',
        'zip',
        'country',

        'home_phone',
        'alt_phone',

        'longsleeveshirt_size_style',
        'teeshirt_size_style',

        'languages',
        'has_reviewed_pi'
    ];

    const HQ_INFO = [
        'on_site',
        'camp_location',
        'emergency_contact'
    ];

    const AGREEMENT_FIELDS = [
        'behavioral_agreement',
    ];

    const RANGER_ADMIN_FIELDS = [
        'vehicle_blacklisted',
    ];

    const EVENT_FIELDS = [
        'active_next_event',
        'on_site',
    ];

    const BMID_FIELDS = [
        'bpguid',
    ];

    // Learning Management System fields
    const LMS_FIELDS = [
        'lms_id',
        'lms_course',
        'lms_course_expiry',
    ];

    const INTAKE_FIELDS = [
        'known_rangers',
        'known_pnvs'
    ];

    const SMS_FIELDS = [
        'sms_on_playa',
        'sms_off_playa',
    ];

    const SMS_ADMIN_FIELDS = [
        'sms_on_playa_verified',
        'sms_off_playa_verified',
        'sms_on_playa_stopped',
        'sms_off_playa_stopped',
        'sms_on_playa_code',
        'sms_off_playa_code',
    ];

    const MESSAGE_FIELDS = [
        'message',
        'message_updated_at'
    ];

    const PERSONNEL_FIELDS = [
        'has_note_on_file',
    ];

    //
    // FIELDS_SERIALIZE & FIELDS_DESERIALIZE elements are
    // 0: array of field names
    // 1: allow fields if the person is the authorized user
    // 2: which roles are allowed the field (if null, allow any)
    //

    const FIELDS_SERIALIZE = [
        [ self::NAME_GENDER_FIELDS ],
        [ self::STATUS_FIELDS ],
        [ self::ROLES_FIELDS ],
        [ self::ACCOUNT_FIELDS ],
        [ self::CALLSIGNS_FIELDS ],
        [ self::CERTIFICATIONS_FIELDS ],
        [ self::MESSAGE_FIELDS, false, [ Role::ADMIN, Role::MANAGE, Role::VC, Role::TRAINER ]],
        [ self::EMAIL_FIELDS, true, [ Role::VIEW_PII, Role::VIEW_EMAIL, Role::VC ] ],
        [ self::PERSONAL_INFO_FIELDS, true, [ Role::VIEW_PII, Role::VC ] ],
        [ self::HQ_INFO, true, [ Role::MANAGE, Role::VIEW_PII, Role::VC ] ],
        [ self::AGREEMENT_FIELDS ],
        [ self::EVENT_FIELDS, true, [ Role::VIEW_PII,  Role::MANAGE, Role::VC, Role::TRAINER, Role::EDIT_BMIDS ] ],
        [ self::BMID_FIELDS, true, [ Role::VIEW_PII,  Role::MANAGE, Role::VC, Role::TRAINER, Role::MENTOR, Role::EDIT_BMIDS ] ],
        [ self::LMS_FIELDS, false, [ Role::ADMIN ]],
        // Note: self is not allowed to see mentor notes
        [ self::INTAKE_FIELDS, false, [ Role::MENTOR, Role::TRAINER, Role::VC, Role::INTAKE ] ],
        [ self::SMS_FIELDS, true, [ Role::ADMIN ]],
        [ self::SMS_ADMIN_FIELDS, true, [ Role::ADMIN ]],
        [ self::RANGER_ADMIN_FIELDS ],
        [ self::PERSONNEL_FIELDS, false, [ Role::ADMIN ]],
    ];

    const FIELDS_DESERIALIZE = [
        [ self::NAME_GENDER_FIELDS, true, [ Role::VC ] ],
        [ self::ACCOUNT_FIELDS, false, [ Role::ADMIN ] ],
        [ self::MESSAGE_FIELDS, false, [ Role::ADMIN, Role::MANAGE, Role::VC, Role::TRAINER ]],
        [ self::CERTIFICATIONS_FIELDS, false, [ Role::VC ]],
        [ self::STATUS_FIELDS, false, [  Role::MENTOR, Role::VC ] ],
        [ self::ROLES_FIELDS, false, [ Role::MENTOR, Role::VC ] ],
        [ self::CALLSIGNS_FIELDS, false, [ Role::MENTOR, Role::VC] ],
        [ self::EMAIL_FIELDS, true, [ Role::VC ] ],
        [ self::PERSONAL_INFO_FIELDS, true, [ Role::VC ] ],
        [ self::HQ_INFO, true, [ Role::MANAGE, Role::VIEW_PII, Role::VC ]],
        [ self::AGREEMENT_FIELDS, true, [ Role::ADMIN ]],
        [ self::EVENT_FIELDS, false, [ Role::MANAGE, Role::VC, Role::TRAINER, Role::MENTOR ]],
        [ self::BMID_FIELDS, true, [  Role::EDIT_BMIDS ] ],
        [ self::LMS_FIELDS, false, [ Role::ADMIN ]],
        [ self::INTAKE_FIELDS, false, [ Role::INTAKE, Role::VC ] ],
        [ self::SMS_FIELDS, true, [ Role::ADMIN ]],
        [ self::SMS_ADMIN_FIELDS, false, [ Role::ADMIN ]],
        [ self::PERSONNEL_FIELDS, false, [ Role::ADMIN ]],
        [ self::RANGER_ADMIN_FIELDS, false, [ Role::ADMIN ]],
    ];

    public function buildFields(array $fieldGroups, $authorizedUser): array
    {
        $fields = [ ];

        if ($authorizedUser) {
            $isUser = ($this->record->id == $authorizedUser->id);
            $isAdmin = $authorizedUser->hasRole(Role::ADMIN);
        } else {
            $isUser = false;
            $isAdmin = false;
        }

        foreach ($fieldGroups as $group) {
            $anyone = count($group) == 1;
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

            if ($allow        // anyone can use this
                || $isAdmin                 // the admin can do anything
                || ($authorizedUser && $roles && $authorizedUser->hasRole($roles))
            ) {
                $fields = array_merge($fields, $group[0]);
            }
        }

        return $fields;
    }

    public function serialize(Person $authorizedUser = null): array
    {
        return $this->buildFields(self::FIELDS_SERIALIZE, $authorizedUser);
    }

    public function deserialize(Person $authorizedUser = null): array
    {
        return $this->buildFields(self::FIELDS_DESERIALIZE, $authorizedUser);
    }
}

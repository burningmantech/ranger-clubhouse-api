<?php

/*
 * NOTE: when adding new columns to the person table, there are three
 * places the column should be added to:
 *
 * - The $fillable array in this file
 * - in app/Http/Filters/PersonFilter.php
 * - on the frontend app/models/person.js
 */

namespace App\Models;

use App\Attributes\BlankIfEmptyAttribute;
use App\Attributes\NullIfEmptyAttribute;
use App\Attributes\PhoneAttribute;
use App\Helpers\SqlHelper;
use App\Jobs\OnlineCourseSyncPersonJob;
use App\Lib\Agreements;
use Carbon\Carbon;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Sanctum\HasApiTokens;
use Normalizer;
use NumberFormatter;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class Person extends ApiModel implements AuthenticatableContract, AuthorizableContract, JWTSubject
{
    use Authenticatable, Authorizable, HasApiTokens, Notifiable;

    // How long is the reset password token good for? (in seconds)
    const RESET_PASSWORD_EXPIRE = (3600 * 2);

    // How long is the pnv invite token sent in the welcome email good for? (in seconds)
    const PNV_INVITATION_EXPIRE = (3600 * 24 * 14);

    // How many years should a callsign be retained after the person passed on.
    const GRIEVING_PERIOD_YEARS = 5;

    // For resetting and adding roles & positions for new users
    const REMOVE_ALL = 0;
    const ADD_NEW_USER = 1;

    const ACTIVE = 'active';
    const ALPHA = 'alpha';
    const AUDITOR = 'auditor';
    const BONKED = 'bonked';
    const DECEASED = 'deceased';
    const DISMISSED = 'dismissed';
    const INACTIVE = 'inactive';
    const INACTIVE_EXTENSION = 'inactive extension';
    const NON_RANGER = 'non ranger';
    const PAST_PROSPECTIVE = 'past prospective';
    const PROSPECTIVE = 'prospective';
    const RESIGNED = 'resigned';
    const RETIRED = 'retired';
    const SUSPENDED = 'suspended';
    const UBERBONKED = 'uberbonked';

    // Deprecated statuses - no longer used, retained for historical purposes
    const PROSPECTIVE_WAITLIST = 'prospective waitlist';
    const VINTAGE = 'vintage';

    /*
     * Statuses consider 'live' or still active account allowed
     * to login, and do stuff.
     * Used by App\Validator\StateForCountry & BroadcastController
     */

    const LIVE_STATUSES = [
        Person::ACTIVE,
        Person::ALPHA,
        Person::INACTIVE,
        Person::INACTIVE_EXTENSION,
        Person::NON_RANGER,
        Person::PAST_PROSPECTIVE,
        Person::PROSPECTIVE,
        Person::RETIRED,
    ];

    const ACTIVE_STATUSES = [
        Person::ACTIVE,
        Person::INACTIVE,
        Person::INACTIVE_EXTENSION,
        Person::RETIRED
    ];

    /*
     * Locked status are those which the account cannot be allowed
     * to logged into (either temporarily or permanently), and which
     * should not receive messages.
     */

    const LOCKED_STATUSES = [
        Person::DECEASED,
        Person::DISMISSED,
        Person::RESIGNED,
        Person::SUSPENDED,
        Person::UBERBONKED,
    ];

    /*
     * No messages status are those that should not receive any messages
     * either Clubhouse Messages or from the RBS
     */

    const NO_MESSAGES_STATUSES = [
        Person::BONKED,
        Person::DECEASED,
        Person::DISMISSED,
        Person::PAST_PROSPECTIVE,
        Person::RESIGNED,
        Person::SUSPENDED,
        Person::UBERBONKED
    ];

    /*
     * No street address required statuses. To deal with legacy accounts.
     */

    const ONLY_BASIC_PII_REQUIRED_STATUSES = [
        self::DECEASED,
        self::DISMISSED,
        self::RESIGNED,
        self::RETIRED,
    ];

    const DEACTIVATED_STATUSES = [
        Person::BONKED,
        Person::DECEASED,
        Person::DISMISSED,
        Person::PAST_PROSPECTIVE,
        Person::RESIGNED,
        Person::UBERBONKED
    ];

    /*
     * Gender identities.
     */

    const GENDER_CIS_FEMALE = 'cis-female';
    const GENDER_CIS_MALE = 'cis-male';
    const GENDER_CUSTOM = 'custom';     // Used when the person wants to state a gender not listed. gender_custom is used.
    const GENDER_FEMALE = 'female';
    const GENDER_FLUID = 'fluid';
    const GENDER_MALE = 'male';
    const GENDER_NONE = ''; // Not stated
    const GENDER_NON_BINARY = 'non-binary';
    const GENDER_QUEER = 'queer';
    const GENDER_TRANS_FEMALE = 'trans-female';
    const GENDER_TRANS_MALE = 'trans-male';
    const GENDER_TWO_SPIRIT = 'two-spirit';

    /**
     * The database table name.
     * @var string
     */

    protected $table = 'person';
    protected bool $auditModel = true;

    public array $auditExclude = [
        'password',
        'tpassword',
        'tpassword_expire',
        'logged_in_at',
        'last_seen_at',
        'callsign_normalized',
        'callsign_soundex'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'tpassword',
        'tpassword_expire',
        'callsign_normalized',
        'callsign_soundex',

        'pivot' // Exclude pivot table references when building JSON response
    ];

    protected $casts = [
        'behavioral_agreement' => 'boolean',
        'callsign_approved' => 'boolean',
        'created_at' => 'datetime',
        'date_verified' => 'date',
        'has_note_on_file' => 'boolean',
        'last_seen_at' => 'datetime',
        'logged_in_at' => 'datetime',
        'message_updated_at' => 'datetime',
        'on_site' => 'boolean',
        'pi_reviewed_for_dashboard_at' => 'datetime',
        'reviewed_pi_at' => 'datetime',
        'status_date' => 'date',
        'updated_at' => 'datetime',
        'used_vanity_change' => 'boolean',
        'vanity_changed_at' => 'datetime',
        'vehicle_blacklisted' => 'boolean',
        'vintage' => 'boolean',
    ];

    /*
     * Do not forget to add the column name to PersonFilter as well.
     */

    protected $fillable = [
        'alt_phone',
        'apt',
        'behavioral_agreement',
        'bpguid',
        'callsign',
        'callsign_approved',
        'callsign_pronounce',
        'camp_location',
        'city',
        'country',
        'date_verified',
        'email',
        'emergency_contact',
        'employee_id',
        'first_name',
        'formerly_known_as',
        'gender_custom',
        'gender_identity',
        'has_note_on_file',
        'has_reviewed_pi',  // Pseudo field
        'home_phone',
        'is_bouncing',
        'known_pnvs',
        'known_rangers',
        'languages',
        'last_name',
        'lms_id',
        'lms_username',
        'long_sleeve_swag_id',
        'message',
        'mi',
        'on_site',
        'preferred_name',
        'pronouns',
        'pronouns_custom',
        'reviewed_pi_at',
        'sfuid',
        'sms_off_playa',
        'sms_off_playa_code',
        'sms_off_playa_stopped',
        'sms_off_playa_verified',
        'sms_on_playa',
        'sms_on_playa_code',
        'sms_on_playa_stopped',
        'sms_on_playa_verified',
        'state',
        'status',
        'status_date',
        'street1',
        'street2',
        'tshirt_secondary_swag_id',
        'tshirt_swag_id',
        'updated_at',
        'used_vanity_change',
        'vehicle_blacklisted',
        'vintage',
        'zip',
    ];

    // Various associated person tables
    const ASSOC_TABLES = [
        'access_document',
        'action_logs',
        'alert_person',
        'asset_person',
        'bmid',
        'broadcast_message',
        'mail_log',
        'manual_review',
        'mentee_status',
        'person_certification',
        'person_event',
        'person_intake',
        'person_intake_note',
        'person_language',
        'person_mentor',
        'person_message',
        'person_online_course',
        'person_position',
        'person_position_log',
        'person_role',
        'person_slot',
        'person_swag',
        'person_team',
        'person_team_log',
        'radio_eligible',
        'team_manager',
        'timesheet',
        'timesheet_log',
        'timesheet_missing',
        'trainee_note',
        'trainee_status',
        'trainer_status'
    ];

    const GENERAL_VALIDATIONS = [
        'alt_phone' => 'sometimes|string|max:25',
        'callsign' => 'required|string|max:64',
        'callsign_pronounce' => 'sometimes|string|nullable|max:200',
        'camp_location' => 'sometimes|string|nullable|max:200',
        'email' => 'required|string|max:50',
        'first_name' => 'required|string|max:25',
        'formerly_known_as' => 'sometimes|string|nullable|max:200',
        'gender_custom' => 'sometimes|string|nullable|max:32',
        'gender_type' => 'sometimes|string',
        'has_reviewed_pi' => 'sometimes|boolean',
        'home_phone' => 'sometimes|string|max:25',
        'last_name' => 'required|string|max:25',
        'mi' => 'sometimes|string|nullable|max:10',
        'preferred_name' => 'sometimes|string|nullable|max:30',
        'pronouns' => 'sometimes|string|nullable',
        'pronouns_custom' => 'sometimes|string|nullable',
        'status' => 'required|string',
    ];

    const ADDRESS_VALIDATIONS = [
        'street1' => 'required|string|nullable|max:128',
        'street2' => 'sometimes|string|nullable|max:128',
        'apt' => 'sometimes|string|nullable|max:10',
        'city' => 'required|string|max:50',
        'state' => 'state_for_country:live_only',
        'country' => 'required|string|max:25',
    ];

    const ALL_VALIDATIONS = [
        ...self::GENERAL_VALIDATIONS,
        ...self::ADDRESS_VALIDATIONS
    ];

    protected $rules = self::ALL_VALIDATIONS;

    protected $attributes = [
        'street2' => '',
        'mi' => '',
        'alt_phone' => '',
        'gender_identity' => self::GENDER_NONE,
        'preferred_name' => '',
    ];

    public bool $has_reviewed_pi = false;

    /**
     * The roles the person holds
     * @var ?array
     */

    public ?array $roles = null;
    public array $rolesById = [];

    /**
     * The raw / un-massaged roles a person holds
     * @var ?array
     */

    public ?array $trueRoles;
    public array $trueRolesById = [];

    /**
     * The raw effective roles not zapped by an unsigned NDA.
     *
     * @var array|null
     */

    public ?array $rawRolesById = [];

    /*
     * The languages the person speaks. (handled thru class PersonLanguage)
     * @var string
     */

    public $languages;

    public function tshirt(): BelongsTo
    {
        return $this->belongsTo(Swag::class, 'tshirt_swag_id');
    }

    public function tshirt_secondary(): BelongsTo
    {
        return $this->belongsTo(Swag::class, 'tshirt_secondary_swag_id');
    }

    public function long_sleeve(): BelongsTo
    {
        return $this->belongsTo(Swag::class, 'long_sleeve_swag_id');
    }

    /**
     * Setup various before save or create callback methods
     */

    public static function boot(): void
    {
        parent::boot();

        self::creating(function ($model) {
            // Set the create date to the current time
            $model->created_at = now();
        });

        self::saving(function ($model) {
            if ($model->isDirty('status')) {
                $model->status_date = now();
            }

            if ($model->isDirty('message')) {
                $model->message_updated_at = now();
            }

            if ($model->pronouns != 'custom') {
                $model->pronouns_custom = '';
            }

            // Clear the bouncing flag when the email changes.
            if ($model->isDirty('email')) {
                $model->is_bouncing = false;
            }

            if ($model->isDirty('used_vanity_change') && $model->used_vanity_change) {
                $model->vanity_changed_at = now();
            }

            if ($model->isDirty('gender_custom')
                && $model->gender_identity == self::GENDER_CUSTOM
                && empty($model->gender_custom)) {
                $model->gender_identity = self::GENDER_NONE;
            }

            /*
             * When the status is updated to Past Prospective and the callsign is
             * not being changed, reset the callsign and un-approve it.
             */

            if ($model->isDirty('status') && $model->status == Person::PAST_PROSPECTIVE) {
                if (!$model->isDirty('callsign')) {
                    $model->resetCallsign();
                }
                $model->callsign_approved = false;
            }
        });

        self::updated(function (Person $model) {
            if (!empty($model->lms_id)
                && $model->wasChanged(['callsign', 'email', 'first_name', 'last_name'])) {
                OnlineCourseSyncPersonJob::dispatch($model);
            }

            if ($model->wasChanged('status')) {
                $changed = $model->getAuditedValues()['status'] ?? null;
                if ($changed) {
                    $model->changeStatus($changed[0], $model->auditReason);
                }
            }
        });
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return int
     */

    public function getJWTIdentifier(): int
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function person_position(): HasMany
    {
        return $this->hasMany(PersonPosition::class);
    }

    public function person_photo(): BelongsTo
    {
        return $this->belongsTo(PersonPhoto::class);
    }

    public function person_role(): HasMany
    {
        return $this->hasMany(PersonRole::class);
    }

    public function email_history(): HasMany
    {
        return $this->hasMany(EmailHistory::class);
    }

    /**
     * Find an account by its email.
     *
     * @param string $email
     * @return Person|null
     */

    public static function findByEmail(string $email): ?Person
    {
        return self::where('email', $email)->first();
    }

    /**
     * Find a record by callsign
     *
     * @param string $callsign
     * @return Person|null
     */

    public static function findByCallsign(string $callsign): ?Person
    {
        return self::where('callsign_normalized', self::normalizeCallsign($callsign))->first();
    }

    /**
     * Look up an account id by its callsign
     *
     * @param string $callsign
     * @return int|null
     */

    public static function findIdByCallsign(string $callsign): ?int
    {
        $row = self::select('id')->where('callsign_normalized', self::normalizeCallsign($callsign))->first();
        if ($row) {
            return $row->id;
        }

        return null;
    }

    /**
     * Find a person by their LMS ID.
     *
     * @param string $lmsId
     * @return ?Person
     */

    public static function findByLmsID(string $lmsId): ?Person
    {
        return self::where('lms_id', $lmsId)->first();
    }

    /**
     * Attempt to save or create a record.
     *
     * @param $options
     * @return bool
     */
    public function save($options = []): bool
    {
        $isNew = !$this->exists;

        if ($isNew) {
            // Creating record = require callsign & email
            $this->rules['callsign'] = 'required|string|unique:person,callsign';
            $this->rules['email'] = 'required|string|unique:person,email';
        } else {
            // Allow the Admins and VCs to bypass personal info validations to deal with
            // moldy defunct accounts with little PII
            if (in_array($this->status, self::ONLY_BASIC_PII_REQUIRED_STATUSES)
                && (!Auth::id() || Auth::user()?->hasRole([Role::ADMIN, Role::VC]))) {
                $this->rules = self::GENERAL_VALIDATIONS;
            }

            if ($this->isDirty('callsign')) {
                // updating a callsign on an existing record
                $this->rules['callsign'] = 'required|string|unique:person,callsign,' . $this->id;
            }

            if ($this->isDirty('email')) {
                $this->rules['email'] = 'required|string|unique:person,email,' . $this->id;
            }
        }

        $result = parent::save($options);

        // Reset the validations in the case the record is acted upon further this session.
        $this->rules = self::ALL_VALIDATIONS;

        return $result;
    }

    /**
     * Bulk lookup by callsigns
     * return an associative array index by callsign
     *
     * @param array $callsigns
     * @return mixed
     */

    public static function findAllByCallsigns(array $callsigns): mixed
    {
        $normalizedCallsigns = array_map(fn($name) => Person::normalizeCallsign($name), $callsigns);
        $rows = self::whereIn('callsign_normalized', $normalizedCallsigns)->get();

        return $rows->reduce(function ($keys, $row) {
            $keys[strtolower($row->callsign_normalized)] = $row;
            return $keys;
        }, []);
    }

    /**
     * Does a record exist with the given email?
     *
     * @param string $email
     * @return bool
     */

    public static function emailExists(string $email): bool
    {
        return self::where('email', $email)->exists();
    }

    /**
     * Find people based on criteria:
     * query: callsign to search for
     * statuses: list of statuses to match (comma separated)
     * limit: number of results to limit query to
     * offset: record offset in search
     *
     * @param array $query
     * @return array
     */

    public static function findForQuery(array $query): array
    {
        $sql = self::orderBy('callsign');
        $callsign = $query['callsign'] ?? null;
        $statuses = $query['statuses'] ?? null;
        $excludeStatuses = $query['exclude_statuses'] ?? null;

        if ($callsign) {
            $sql->where('callsign_normalized', self::normalizeCallsign($callsign));
        }

        if ($statuses) {
            $sql->whereIn('status', explode(',', $statuses));
        }

        if ($excludeStatuses) {
            $sql->whereNotIn('status', explode(',', $excludeStatuses));
        }


        $total = $sql->count();

        $limit = $query['limit'] ?? 50;
        $sql->limit($limit);

        $offset = $query['offset'] ?? null;
        if ($offset) {
            $sql = $sql->offset($offset);
        }

        return [
            'people' => $sql->get(),
            'total' => $total,
            'limit' => $limit
        ];
    }

    /**
     * Normalize a callsign by removing spaces, and converting to lowercase
     *
     * @param string $callsign
     * @return string
     */

    public static function normalizeCallsign(string $callsign): string
    {
        $callsign = preg_replace('/&/', ' and ', $callsign);
        return strtolower(preg_replace('/[^\w]/', '', self::convertDiacritics($callsign)));
    }

    /**
     * Convert any diacritics into ascii (é -> e, ü -> u)
     *
     * @param string $value
     * @return string
     */

    public static function convertDiacritics(string $value): string
    {
        $value = preg_replace('/©/', '@', $value);
        return preg_replace('/[\x{0300}-\x{036f}]/u', '', Normalizer::normalize($value, Normalizer::FORM_D));
    }

    /**
     * Search for callsign types
     *
     * @param string $query string to match against callsigns
     * @param string $type callsign search type
     * @param int $limit
     * @return mixed person id & callsigns which match
     */

    public static function searchCallsigns(string $query, string $type, int $limit): mixed
    {
        $like = '%' . $query . '%';

        $normalized = self::normalizeCallsign($query);
        $metaphone = metaphone(self::spellOutNumbers($normalized));
        $quoted = SqlHelper::quote($normalized);
        $orderBy = "CASE WHEN callsign_normalized=$quoted THEN CONCAT('01', callsign)";
        $quoted = SqlHelper::quote($metaphone);
        $orderBy .= " WHEN callsign_soundex=$quoted THEN CONCAT('02', callsign)";
        $orderBy .= " ELSE CONCAT('03', callsign) END";

        $sql = DB::table('person')
            ->where(function ($q) use ($query, $like, $normalized, $metaphone) {
                $q->orWhere('callsign_soundex', $metaphone);
                $q->orWhere('callsign_normalized', $normalized);
                $q->orWhere('callsign_normalized', 'like', '%' . $normalized . '%');
            })->limit($limit)
            ->orderBy(DB::raw($orderBy));

        switch ($type) {
            case 'contact':
                return $sql->select('person.id', 'callsign', DB::raw('IF(person.status="inactive", true,false) as is_inactive'), DB::raw('IFNULL(alert_person.use_email,1) as allow_contact'))
                    ->whereIn('status', [Person::ACTIVE, Person::INACTIVE])
                    ->leftJoin('alert_person', function ($join) {
                        $join->whereRaw('alert_person.person_id=person.id');
                        $join->where('alert_person.alert_id', '=', Alert::RANGER_CONTACT);
                    })->get()->toArray();

            // Trying to send a clubhouse message
            case 'message':
                return $sql->whereIn('status', [Person::ACTIVE, Person::INACTIVE, Person::ALPHA])->get(['id', 'callsign']);

            // Search all users
            case 'all':
                return $sql->get(['id', 'callsign']);
        }

        throw new InvalidArgumentException("Unknown type [$type]");
    }

    /**
     * Is the given password the one for this account?
     *
     * @param string $password
     * @return bool
     */

    public function isValidPassword(string $password): bool
    {
        $encyptedPw = $this->password;
        if (!str_contains($encyptedPw, ':')) {
            return false;
        }

        list($salt, $sha) = explode(':', $encyptedPw);
        $hashedPw = sha1($salt . $password);

        return ($hashedPw == $sha);
    }

    /**
     * Set a new password and clear out the temporary login token.
     *
     * @param string $password
     * @return bool
     */

    public function changePassword(string $password): bool
    {
        $salt = self::generateRandomString();
        $sha = sha1($salt . $password);

        $this->password = "$salt:$sha";

        // Clear out the temporary login token
        $this->tpassword = '';
        $this->tpassword_expire = 0;
        return $this->saveWithoutValidation();
    }


    /**
     * Create, or extend, a temporary login token used to reset a password or, for an invited
     * PNV, set a password.
     *
     * @param int $expireDuration time in seconds the token is good for.
     * @return string
     */

    public function createTemporaryLoginToken(int $expireDuration = self::RESET_PASSWORD_EXPIRE): string
    {
        $timestamp = now()->timestamp;
        // Generate a new token if none exists or the token has expired
        if (empty($this->tpassword) || $timestamp > $this->tpassword_expire) {
            $this->tpassword = sprintf("%04x%s", $this->id, self::generateRandomString());
        }
        $this->tpassword_expire = $timestamp + $expireDuration;
        $this->saveWithoutValidation();

        return $this->tpassword;
    }

    /**
     * Return the role ids (used by Person record serialization)
     *
     * @return ?array
     */

    public function getRolesAttribute(): ?array
    {
        return $this->roles;
    }

    /**
     * Retrieve the person's effective roles.
     *
     * Add MANAGE if person has MANAGE_ON_PLAYA and if LoginManageOnPlayaEnabled is true
     * Add TRAINER if person has TRAINER_SEASONAL and if TrainingSeasonalRoleEnabled is true
     *
     * User has to have the Ranger NDA signed if an LM role is in effect, otherwise kill
     * all the roles (unless the user is a Tech Ninja) until the NDA has been agreed to.
     */

    public function retrieveRoles(): void
    {
        if ($this->roles) {
            return;
        }

        $cachedRoles = PersonRole::getCache($this->id);
        if ($cachedRoles) {
            $this->setCachedRoles($cachedRoles);
            return;
        }

        $grantedIds = DB::table('person_role')
            ->where('person_id', $this->id)
            ->pluck('role_id')
            ->toArray();

        // Find the granted roles via assigned positions
        $positionRoles = DB::table('person_position')
            ->select('role_id', 'require_training_for_roles', 'position.id', 'position.training_position_id')
            ->join('position_role', 'position_role.position_id', 'person_position.position_id')
            ->join('position', 'position.id', 'person_position.position_id')
            ->where('person_id', $this->id)
            ->get();

        $roleIds = [];
        $year = current_year();
        foreach ($positionRoles as $pos) {
            if ($pos->require_training_for_roles) {
                if ($pos->training_position_id && Training::didPersonPassForYear($this, $pos->training_position_id, $year)) {
                    // Person has passed the appropriate ART, grant the roles.
                    $roleIds[] = $pos->role_id;
                }
            } else {
                $roleIds[] = $pos->role_id;
            }
        }

        // Find the granted roles via assigned positions
        $teamRoles = DB::table('person_team')
            ->join('team_role', 'team_role.team_id', 'person_team.team_id')
            ->where('person_team.person_id', $this->id)
            ->pluck('role_id')
            ->toArray();

        $this->rolesById = array_fill_keys(array_unique(array_merge($roleIds, $teamRoles, $grantedIds)), true);

        // Save off the roles before mucking around.
        $this->trueRolesById = $this->rolesById;
        $this->trueRoles = array_keys($this->rolesById);

        $haveManage = $this->rolesById[Role::MANAGE] ?? false;
        $lmopEnabled = setting('LoginManageOnPlayaEnabled');

        if (!$haveManage
            && isset($this->rolesById[Role::MANAGE_ON_PLAYA])
            && $lmopEnabled) {
            $this->rolesById[Role::MANAGE] = true;
            $haveManage = true;
        }

        if (!isset($this->rolesById[Role::TRAINER])
            && isset($this->rolesById[Role::TRAINER_SEASONAL])
            && setting('TrainingSeasonalRoleEnabled')) {
            $this->rolesById[Role::TRAINER] = true;
        }

        foreach ([Role::MEGAPHONE_EMERGENCY_ONPLAYA, Role::MEGAPHONE_TEAM_ONPLAYA] as $role) {
            if (isset($this->rolesById[$role]) && !$lmopEnabled) {
                unset($this->rolesById[$role]);
            }
        }

        $this->rawRolesById = $this->rolesById;

        $noCache = false;
        if ($haveManage && !isset($this->rolesById[Role::TECH_NINJA])) {
            // Kill the roles if the NDA is not signed, the NDA document exists and this is not a Ground Hog Day server
            if (!config('clubhouse.GroundhogDayTime')
                && !PersonEvent::isSet($this->id, 'signed_nda')
                && Document::haveTag(Document::DEPT_NDA_TAG)) {
                // Don't allow the person to do anything until the NDA is signed.
                $this->rolesById = [];
                $this->trueRolesById = [];
                $this->trueRoles = [];
                $noCache = true;
            }
        }

        $this->roles = array_keys($this->rolesById);
        if (!$noCache) {
            PersonRole::putCache($this->id, [$this->roles, $this->trueRoles, $this->rawRolesById]);
        }
    }

    public function setCachedRoles(array $cachedRoles): void
    {
        list ($effectiveRoles, $trueRoles, $rawRolesById) = $cachedRoles;
        $this->roles = $effectiveRoles;
        $this->rolesById = array_fill_keys($this->roles, true);
        $this->trueRoles = $trueRoles;
        $this->trueRolesById = array_fill_keys($this->trueRoles, true);
        $this->rawRolesById = $rawRolesById;
    }

    /**
     * Check to see if the person has an effective role or roles.
     *
     * @param array|int $role
     * @return bool true if the person has the role
     */

    public function hasRole(array|int $role): bool
    {
        if ($this->roles === null) {
            $this->retrieveRoles();
        }

        if (!is_array($role)) {
            return isset($this->rolesById[$role]);
        }

        foreach ($role as $r) {
            if (isset($this->rolesById[$r])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check to see if the person has an un-massaged role.
     *
     * @param array|int $role
     * @return bool true if the person has the role
     */

    public function hasTrueRole(array|int $role): bool
    {
        if ($this->roles === null) {
            $this->retrieveRoles();
        }

        if (!is_array($role)) {
            return isset($this->trueRolesById[$role]);
        }

        foreach ($role as $r) {
            if (isset($this->trueRolesById[$r])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check to see if the person has an effective role not zapped by an unsigned NDA.
     *
     * @param array|int $role
     * @return bool true if the person has the role
     */

    public function hasRawRole(array|int $role): bool
    {
        if ($this->roles === null) {
            $this->retrieveRoles();
        }

        if (!is_array($role)) {
            return isset($this->rawRolesById[$role]);
        }

        foreach ($role as $r) {
            if (isset($this->rawRolesById[$r])) {
                return true;
            }
        }

        return false;
    }


    /**
     * Is the person an Admin?
     *
     * @return bool
     */

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    /**
     * Is the person a prospective new volunteer? (prospector or alpha status)
     *
     * @return bool
     */

    public function isPNV(): bool
    {
        $status = $this->status;

        return ($status == Person::PROSPECTIVE || $status == Person::ALPHA);
    }

    /**
     * Is the person an auditor?
     *
     * @return bool
     */

    public function isAuditor(): bool
    {
        return ($this->status == Person::AUDITOR);
    }

    /**
     * Creates a 10 character random alphanumeric string. Use primarily to generate
     * a temporary password.
     *
     * @return string
     */

    public static function generateRandomString(): string
    {
        $length = 10;
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $max = strlen($characters) - 1;
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $characters[mt_rand(0, $max)];
        }

        return $token;
    }

    /**
     * Obtain the pseudo-field languages
     *
     * @return mixed
     */

    public function getLanguagesAttribute(): mixed
    {
        return $this->languages;
    }

    /**
     * Set the pseudo-field languages
     *
     * @param $value
     * @return void
     */

    public function setLanguagesAttribute($value): void
    {
        $this->languages = $value;
    }

    /**
     * Set pseudo-field has_reviewed_pi.
     *
     * @param $value
     * @return void
     */

    public function setHasReviewedPiAttribute($value): void
    {
        $this->has_reviewed_pi = $value;
    }

    /**
     * Obtain the created_at field.
     *
     * Accounts created prior to 2010 have a 0000-00-00 date. Return null if that's
     * the case.
     *
     * @return Carbon|null
     */

    public function getCreatedAtAttribute(): ?Carbon
    {
        if ($this->attributes == null) {
            return null;
        }

        $date = $this->attributes['created_at'] ?? null;

        if ($date == null) {
            return null;
        }

        $date = Carbon::parse($date);
        if ($date->year <= 0) {
            return null;
        }

        return $date;
    }

    /**
     * Change the account status and adjust the positions & roles if need be. Mostly copied from Clubhouse 1.
     * Invoked  from the updated model callback -- avoid calling this method directly.
     *
     * Depending on the new status, the following actions may be taken:
     * - Grant or revocation of positions and teams.
     * - Addition or removal of permissions
     * - Removal of upcoming shift sign-ups.
     * - The approved, non-vintage callsign will be added to the handle reservation list for deceased individuals.
     * - Clear the asset authorization flag for "locked" account statuses.
     *
     * TODO figure out a better way to do this.
     *
     * @param string $oldStatus
     * @param string $reason
     */

    public function changeStatus(string $oldStatus, string $reason): void
    {
        $newStatus = $this->status;
        if ($newStatus == $oldStatus) {
            return;
        }

        $personId = $this->id;
        $this->status = $newStatus;

        PersonStatus::record($this->id, $oldStatus, $newStatus, $reason, Auth::id());

        $changeReason = $reason . " new status $newStatus";

        switch ($newStatus) {
            case Person::ACTIVE:
                // grant the new ranger all the basic positions
                $addIds = Position::where('all_rangers', true)->pluck('id');
                PersonPosition::addIdsToPerson($personId, $addIds, $changeReason);

                // Ensure any default permissions are added
                $addIds = Role::where('new_user_eligible', true)->pluck('id');
                PersonRole::addIdsToPerson($personId, $addIds, $changeReason);

                // First-year Alphas get the Dirt - Shiny Penny position
                if ($oldStatus == Person::ALPHA) {
                    PersonPosition::addIdsToPerson($personId, [Position::DIRT_SHINY_PENNY], $changeReason);
                }
                break;

            case Person::ALPHA:
                // grant the alpha position
                PersonPosition::addIdsToPerson($personId, [Position::ALPHA], $changeReason);
                break;

            case Person::UBERBONKED:
            case Person::DECEASED:
            case Person::DISMISSED:
            case Person::RESIGNED:
                // Remove all positions
                PersonPosition::resetPositions($personId, $changeReason, Person::REMOVE_ALL);

                // Remove all roles
                PersonRole::resetRoles($personId, $changeReason, Person::REMOVE_ALL);

                // Remove all teams
                PersonTeam::removeAllFromPerson($personId, $changeReason);

                // Remove any signups.
                Schedule::removeFutureSignUps($personId, "conversion to $newStatus");
                break;

            case Person::BONKED:
                // Remove all positions
                PersonPosition::resetPositions($personId, $changeReason, Person::REMOVE_ALL);
                Schedule::removeFutureSignUps($personId, 'conversion to bonked');
                break;

            case Person::RETIRED:
                PersonTeam::removeAllFromPerson($personId, $changeReason);
            // fall thru
            case Person::AUDITOR:
            case Person::PROSPECTIVE:
                // Remove all roles, and reset back to the default roles
                PersonRole::resetRoles($personId, $changeReason, Person::ADD_NEW_USER);

                // Remove all positions, and reset back to the default positions
                PersonPosition::resetPositions($personId, $changeReason, Person::ADD_NEW_USER);
                break;

            case Person::PAST_PROSPECTIVE:
                // Remove all positions and permissions
                PersonRole::resetRoles($personId, $changeReason, Person::REMOVE_ALL);
                PersonPosition::resetPositions($personId, $changeReason, Person::REMOVE_ALL);
                PersonTeam::removeAllFromPerson($personId, $changeReason);
                Schedule::removeFutureSignUps($personId, 'conversion to past prospective');
                break;
        }

        if ($newStatus === Person::DECEASED && $this->callsign_approved && !$this->vintage) {
            HandleReservation::recordDeceased($this->callsign);
        }

        if ($oldStatus == Person::ALPHA) {
            // if you're no longer an alpha, you can't sign up for alpha shifts
            PersonPosition::removeIdsFromPerson($personId, [Position::ALPHA], $reason . ' no longer alpha');
        }

        if (in_array($newStatus, self::LOCKED_STATUSES)) {
            $event = PersonEvent::findForPersonYear($this->id, current_year());
            if ($event) {
                $event->asset_authorized = false;
                $event->auditReason = 'locked status ' . $newStatus;
                $event->saveWithoutValidation();
            }
        }
    }

    /**
     * Reset callsign to the last name, first character of first name, and the last two digits of the current year
     * LastFirstYY
     *
     * If the person was bonked, append a 'B'.
     * If the person is an auditor, append a '(NR)'
     * If the new callsign already exits, find one that does not exist by appending a number to the last name.
     *
     * e.g. Jane Smith, year 2019 -> SmithJ19
     *           or Smith1J19, Smith2J19, etc if SmithJ19 already exists.
     *
     * @return bool true if the callsign was successfully reset
     */

    public function resetCallsign(): bool
    {
        $lastName = self::convertDiacritics($this->last_name);
        $firstLetter = substr(self::convertDiacritics($this->desired_first_name()), 0, 1);
        $year = current_year() % 100;
        for ($tries = 0; $tries < 10; $tries++) {
            $newCallsign = $lastName;
            if ($tries > 0) {
                $newCallsign .= $tries + 1;
            }
            $newCallsign .= $firstLetter . $year;
            if ($this->status == Person::BONKED) {
                $newCallsign .= 'B';
            } else if ($this->status == Person::AUDITOR) {
                $newCallsign .= '(NR)';
            }

            if (!self::where('callsign', $newCallsign)->exists()) {
                $this->callsign = $newCallsign;
                return true;
            }
        }

        return false;
    }

    /**
     * Store a normalized and metaphone version of the callsign
     *
     * @param string $value
     */

    public function setCallsignAttribute(string $value)
    {
        $value = trim($value);
        $this->attributes['callsign'] = $value;
        $this->attributes['callsign_normalized'] = self::normalizeCallsign($value ?? ' ');
        $this->attributes['callsign_soundex'] = metaphone(self::spellOutNumbers($this->attributes['callsign_normalized']));

        // Update the callsign FKA if the callsign did actually change.
        if ($this->isDirty('callsign')) {
            $oldCallsign = $this->getOriginal('callsign');
            if (!empty($oldCallsign)) {
                $fka = $this->formerly_known_as;
                if (empty($fka)) {
                    $this->formerly_known_as = $oldCallsign;
                } elseif (stripos($fka, $oldCallsign) === false) {
                    $this->formerly_known_as = $fka . ',' . $oldCallsign;
                }
            }
        }
    }

    /**
     * Set the callsign pronunciation column. Remove quotes because people are too "literal" sometimes.
     */

    public function callsignPronounce(): Attribute
    {
        return Attribute::make(
            set: fn($value) => empty($value) ? '' : trim(preg_replace("/['\"]/", '', trim($value)))
        );
    }

    /**
     * Split the FKA into an array
     *
     * @param bool $filter
     * @return array
     */

    public function formerlyKnownAsArray(bool $filter = false): array
    {
        return self::splitCommas($this->formerly_known_as, $filter);
    }

    /**
     * Split the Known PNVs into an array
     *
     * @return array
     */

    public function knownPnvsArray(): array
    {
        return self::splitCommas($this->known_pnvs);
    }

    /**
     * Split the Known Rangers into an array
     *
     * @return array
     */

    public function knownRangersArray(): array
    {
        return self::splitCommas($this->known_rangers);
    }

    /**
     * Has the person reviewed their personal information?
     *
     * @return bool
     */
    public function hasReviewedPi(): bool
    {
        return ($this->pi_reviewed_for_dashboard_at
            && $this->pi_reviewed_for_dashboard_at->year == current_year());
    }

    /**
     * Split a string into an array and filter out callsign indicators
     * (i.e., remove year, bonk indicator, and non-ranger suffixes)
     *
     * @param ?string $str
     * @param bool $filter
     * @return array
     */

    public static function splitCommas(?string $str, bool $filter = false): array
    {
        if (empty($str)) {
            return [];
        }

        $names = preg_split('/\s*,\s*/', trim($str));
        if (!$filter) {
            return $names;
        }

        return array_values(array_filter($names, fn($name) => !preg_match('/\d{2,4}[B]?(\(NR\))?$/', $name)));
    }

    /**
     * Set pronouns_custom field to a string value or empty string
     */

    public function pronounsCustom(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    /**
     * Set pronouns field to a string value or empty string
     */

    public function pronouns(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    /**
     * Set custom gender to blank if empty
     */

    public function genderCustom(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    /**
     * Set the preferred first name to blank if empty
     */

    public function preferredName(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    /**
     * Obtain the first name to use - either the preferred name if present, or the first name.
     *
     * @return string
     */

    public function desired_first_name(): string
    {
        return empty($this->preferred_name) ? $this->first_name : $this->preferred_name;
    }

    /**
     * Set the employee_id fields
     */

    public function employeeId(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function homePhone(): Attribute
    {
        return PhoneAttribute::make();
    }

    public function altPhone(): Attribute
    {
        return PhoneAttribute::make();
    }

    public function smsOnPlaya(): Attribute
    {
        return PhoneAttribute::make();
    }

    public function smsOffPlaya(): Attribute
    {
        return PhoneAttribute::make();
    }

    public function street2(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function mi(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function apt(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }


    /**
     * Spell out all the numbers in a string.
     * e.g., 3pio -> threepio, hubcap92 -> hubcapninetytwo
     *
     * @param string $word
     * @return string
     */

    public static function spellOutNumbers(string $word): string
    {
        $formatter = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        return preg_replace_callback('/\d+/', fn($number) => $formatter->format((int)$number[0]), $word);
    }
}

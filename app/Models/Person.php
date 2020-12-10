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

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use InvalidArgumentException;
use Tymon\JWTAuth\Contracts\JWTSubject;

use App\Helpers\SqlHelper;

use Carbon\Carbon;

use App\Models\Alert;
use App\Models\ApiModel;
use App\Models\PersonEvent;
use App\Models\PersonPhoto;
use App\Models\PersonPosition;
use App\Models\PersonRole;
use App\Models\PersonStatus;
use App\Models\Role;


class Person extends ApiModel implements JWTSubject, AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable, Notifiable;

    const RESET_PASSWORD_EXPIRE = (3600 * 2);

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
     * to logged into (either tempoarily or permanently), and which
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
     * either Clubhouse Messaging or the RBS
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


    /**
     * The database table name.
     * @var string
     */
    protected $table = 'person';
    protected $auditModel = true;

    public $auditExclude = ['password', 'tpassword', 'tpassword_expire', 'logged_in_at'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'tpassword',
        'tpassword_expire'
    ];

    protected $casts = [
        'active_next_event' => 'boolean',
        'behavioral_agreement' => 'boolean',
        'callsign_approved' => 'boolean',
        'has_note_on_file' => 'boolean',
        'on_site' => 'boolean',
        'osha10' => 'boolean',
        'osha30' => 'boolean',
        'vehicle_blacklisted' => 'boolean',

        'create_date' => 'datetime',
        'date_verified' => 'date',
        'status_date' => 'date',
        'message_updated_at' => 'datetime',
        'timestamp' => 'timestamp',
        'logged_in_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'lms_course_expiry' => 'datetime',

        'reviewed_pi_at' => 'datetime',
    ];

    /*
     * Do not forget to add the column name to PersonFilter as well.
     */

    protected $fillable = [
        'callsign',
        'callsign_approved',
        'formerly_known_as',
        'callsign_pronounce',

        'status',
        'status_date',
        'timestamp',

        'date_verified',
        'create_date',


        'vintage',
        'behavioral_agreement',

        'gender',
        'pronouns',
        'pronouns_custom',

        'message',

        'email',
        'first_name',
        'mi',
        'last_name',
        'street1',
        'street2',
        'apt',
        'city',
        'state',
        'zip',
        'country',

        'home_phone',
        'alt_phone',

        'on_site',
        'longsleeveshirt_size_style',
        'teeshirt_size_style',
        'emergency_contact',
        'camp_location',

        'reviewed_pi_at',
        'has_reviewed_pi',  // Pseudo field

        'vehicle_blacklisted',

        // various external services identifiers
        'bpguid',
        'sfuid',
        'lms_id',
        'lms_course',
        'lms_course_expiry',

        'active_next_event',
        'has_note_on_file',

        'known_rangers',
        'known_pnvs',

        // 'meta' objects
        'languages',

        // SMS fields
        'sms_on_playa',
        'sms_off_playa',
        'sms_on_playa_verified',
        'sms_off_playa_verified',
        'sms_on_playa_stopped',
        'sms_off_playa_stopped',
        'sms_on_playa_code',
        'sms_off_playa_code',

        // Certifications
        'osha10',
        'osha30',
    ];

    const SEARCH_FIELDS = [
        'email',
        'name',
        'first_name',
        'last_name',
        'callsign',
        'formerly_known_as'
    ];

    // Various associated person tables
    const ASSOC_TABLES = [
        //'access_document_changes',
        'access_document_delivery',
        'access_document',
        'action_logs',
        'alert_person',
        'asset_person',
        'bmid',
        'broadcast_message',
        //'contact_log',
        'manual_review',
        'mentee_status',
        'person_event',
        'person_intake',
        'person_intake_note',
        'person_language',
        'person_mentor',
        'person_message',
        'person_online_training',
        'person_position',
        'person_role',
        'person_slot',
        'radio_eligible',
        'timesheet',
        'timesheet_log',
        'timesheet_missing',
        'trainee_note',
        'trainee_status',
        'trainer_status'
    ];


    protected $appends = [
        'roles',
    ];

    protected $rules = [
        'callsign' => 'required|string|max:64',
        'callsign_pronounce' => 'sometimes|string|nullable|max:200',
        'status' => 'required|string',
        'formerly_known_as' => 'sometimes|string|nullable|max:200',

        'first_name' => 'required|string|max:25',
        'mi' => 'sometimes|string|nullable|max:10',
        'last_name' => 'required|string|max:25',

        'email' => 'required|string|max:50',

        'street1' => 'required|string|nullable|max:128',
        'street2' => 'sometimes|string|nullable|max:128',
        'apt' => 'sometimes|string|nullable|max:10',
        'city' => 'required|string|max:50',

        'state' => 'state_for_country:live_only',
        'country' => 'required|string|max:25',

        'home_phone' => 'sometimes|string|max:25',
        'alt_phone' => 'sometimes|string|nullable|max:25',

        'camp_location' => 'sometimes|string|nullable|max:200',
        'gender' => 'sometimes|string|nullable|max:32',
        'pronouns' => 'sometimes|string|nullable',
        'pronouns_custom' => 'sometimes|string|nullable',

        'has_reviewed_pi' => 'sometimes|boolean',
    ];

    public $has_reviewed_pi;

    /*
     * The roles the person holds
     * @var array
     */

    public $roles;

    /*
     * The languages the person speaks. (handled thru class PersonLanguage)
     * @var string
     */

    public $languages;

    /*
     * setup before methods
     */

    public static function boot()
    {
        parent::boot();

        self::creating(function ($model) {
            $model->create_date = now();
        });

        self::saving(function ($model) {
            if ($model->isDirty('message')) {
                $model->message_updated_at = now();
            }

            // Ensure shirts are always set correctly
            if (empty($model->longsleeveshirt_size_style)) {
                $model->longsleeveshirt_size_style = 'Unknown';
            }

            if (empty($model->teeshirt_size_style)) {
                $model->teeshirt_size_style = 'Unknown';
            }

            if ($model->pronouns != 'custom') {
                $model->pronouns_custom = '';
            }

            /*
             * When the status is updated to Past Prospecitve and the callsign is
             * not being changed, reset the the callsign and unapprove it.
             */

            if ($model->isDirty('status')
                && $model->status == Person::PAST_PROSPECTIVE
                && !$model->isDirty('callsign')) {
                $model->resetCallsign();
                $model->callsign_approved = false;
            }
        });
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */

    public function getJWTIdentifier(): string
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

    public function person_position()
    {
        return $this->hasMany(PersonPosition::class);
    }

    public function person_photo()
    {
        return $this->belongsTo(PersonPhoto::class);
    }

    public static function findByEmail(string $email)
    {
        return self::where('email', $email)->first();
    }

    public static function findByCallsign(string $callsign)
    {
        return self::where('callsign', $callsign)->first();
    }

    public static function findIdByCallsign(string $callsign)
    {
        $row = self::select('id')->where('callsign', $callsign)->first();
        if ($row) {
            return $row->id;
        }

        return null;
    }

    public function save($options = [])
    {
        $isNew = !$this->id;
        if ($isNew) {
            // Creating record = require callsign & email
            $this->rules['callsign'] = 'required|string|unique:person,callsign';
            $this->rules['email'] = 'required|string|unique:person,email';
        } else {
            if ($this->isDirty('callsign')) {
                // updating a callsign on an existing record
                $this->rules['callsign'] = 'required|string|unique:person,callsign,' . $this->id;
            }
            if ($this->isDirty('email')) {
                $this->rules['email'] = 'required|string|unique:person,email,' . $this->id;
            }
        }

        $result = parent::save($options);

        if ($isNew) {
            // Kill the rules in case the newly recreated record is updated further, otherwise
            // the callsign & email rules will be acted upon and always fail.
            unset($this->rules['callsign']);
            unset($this->rules['email']);
        }

        return $result;
    }

    /*
     * Bulk lookup by callsigns
     * return an associatve array index by callsign
     */

    public static function findAllByCallsigns(array $callsigns, $toLowerCase = false)
    {
        $rows = self::whereIn('callsign', $callsigns)->get();

        if (!$toLowerCase) {
            return $rows->keyBy('callsign');
        }

        return $rows->reduce(function ($keys, $row) {
            $keys[strtolower($row->callsign)] = $row;
            return $keys;
        }, []);
    }

    public static function emailExists($email)
    {
        return self::where('email', $email)->exists();
    }

    public static function findForQuery($query)
    {
        if (isset($query['query'])) {
            // remove duplicate spaces
            $q = trim(preg_replace('/\s+/', ' ', $query['query']));
            $normalized = self::normalizeCallsign($q);
            $metaphone = metaphone($normalized);

            if (substr($q, 0, 1) == '+') {
                // Search by number
                $q = ltrim($q, '+');
                $person = self::find(intval($q));

                if ($person) {
                    $total = $limit = 1;
                } else {
                    $total = $limit = 0;
                }
                return [
                    'people' => $person ? [$person] : [],
                    'total' => $total,
                    'limit' => $limit
                ];
            }
            $likeQuery = '%' . $q . '%';

            $emailOnly = (stripos($q, '@') !== false);
            if ($emailOnly) {
                // Force to email only search if atsign is present
                $sql = self::where(function ($sql) use ($likeQuery, $q) {
                    $sql->where('email', $q);
                    $sql->orWhere('email', 'like', $likeQuery);
                });
            } elseif (isset($query['search_fields'])) {
                $fields = explode(',', $query['search_fields']);

                $sql = self::where(function ($sql) use ($q, $fields, $likeQuery, $normalized, $metaphone) {
                    foreach ($fields as $field) {
                        if (!in_array($field, self::SEARCH_FIELDS)) {
                            throw new InvalidArgumentException("Search field '$field' is not allowed.");
                        }

                        if ($field == 'name') {
                            $sql->orWhere('first_name', 'like', $likeQuery);
                            $sql->orWhere('last_name', 'like', $likeQuery);

                            if (strpos($q, ' ') !== false) {
                                $name = explode(' ', $q);
                                $sql->orWhere(function ($cond) use ($name) {
                                    $cond->where([
                                        ['first_name', 'like', '%' . $name[0] . '%'],
                                        ['last_name', 'like', '%' . $name[1] . '%']
                                    ]);
                                });
                            }
                        } elseif ($field == 'callsign') {
                            $sql->orWhere('callsign_normalized', $normalized);
                            $sql->orWhere('callsign_normalized', 'like', '%' . $normalized . '%');
                            $sql->orWhere('callsign_soundex', $metaphone);
                        } else {
                            $sql->orWhere($field, 'like', $likeQuery);
                        }
                    }
                });
            } else {
                $sql = self::where('callsign', 'like', $likeQuery);
            }

            $orderBy = "CASE";
            if ($emailOnly) {
                $orderBy .= " WHEN email=" . SqlHelper::quote($q) . " THEN CONCAT('00', callsign)";
                $orderBy .= " WHEN email like " . SqlHelper::quote($likeQuery) . " THEN CONCAT('03', callsign)";
            }
            $orderBy .= " WHEN callsign_normalized=" . SqlHelper::quote($normalized) . " THEN CONCAT('01', callsign)";
            $orderBy .= " WHEN callsign_soundex=" . SqlHelper::quote($metaphone) . " THEN CONCAT('02', callsign)";
            $orderBy .= " ELSE callsign END";

            $sql->orderBy(DB::raw($orderBy));
        } else {
            $sql = self::query();
            $sql->orderBy('callsign');
        }

        if (isset($query['statuses'])) {
            $sql->whereIn('status', explode(',', $query['statuses']));
        }

        if (isset($query['exclude_statuses'])) {
            $sql->whereNotIn('status', explode(',', $query['exclude_statuses']));
        }


        if (isset($query['limit'])) {
            $limit = $query['limit'];
        } else {
            $limit = 50;
        }

        if (isset($query['offset'])) {
            $sql = $sql->offset($query['offset']);
        }

        $total = $sql->count();
        $sql->limit($limit);

        return [
            'people' => $sql->get(),
            'total' => $total,
            'limit' => $limit
        ];
    }

    public static function normalizeCallsign($callsign)
    {
        return preg_replace('/[^\w]/', '', $callsign);
    }

    /**
     * Search for matching callsigns
     *
     *
     * @param string $query string to match against callsigns
     * @param string $type callsign search type
     * @return array person id & callsigns which match
     */

    public static function searchCallsigns($query, $type, $limit)
    {
        $like = '%' . $query . '%';

        $normalized = self::normalizeCallsign($query);
        $metaphone = metaphone($normalized);
        $quoted = SqlHelper::quote($normalized);
        $orderBy = "CASE WHEN callsign_normalized=$quoted THEN CONCAT('!', callsign)";
        $quoted = SqlHelper::quote($metaphone);
        $orderBy .= " WHEN callsign_soundex=$quoted THEN CONCAT('#', callsign)";
        $orderBy .= " ELSE callsign END";

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

    public static function retrievePeopleByLocation($year)
    {
        return self::select(
            'id',
            'callsign',
            'first_name',
            'last_name',
            'status',
            'email',
            'city',
            'state',
            'zip',
            'country',
            DB::raw("EXISTS (SELECT 1 FROM timesheet WHERE person_id=person.id AND YEAR(on_duty)=$year LIMIT 1) as worked"),
            DB::raw("EXISTS (SELECT 1 FROM person_slot JOIN slot ON slot.id=person_slot.slot_id AND YEAR(slot.begins)=$year AND slot.position_id != " . Position::ALPHA . " WHERE person_slot.person_id=person.id LIMIT 1) AS signed_up ")
        )
            ->orderBy('country')
            ->orderBy('state')
            ->orderBy('city')
            ->orderBy('zip')
            ->get();
    }

    public static function retrievePeopleByRole()
    {
        $roleGroups = DB::table('role')
            ->select('role.id as role_id', 'role.title', 'person.id as person_id', 'person.callsign')
            ->join('person_role', 'person_role.role_id', 'role.id')
            ->join('person', 'person.id', 'person_role.person_id')
            ->orderBy('callsign')
            ->get()
            ->groupBy('role_id');

        $roles = [];
        foreach ($roleGroups as $roleId => $group) {
            $roles[] = [
                'id' => $roleId,
                'title' => $group[0]->title,
                'people' => $group->map(function ($row) {
                    return ['id' => $row->person_id, 'callsign' => $row->callsign];
                })->values()
            ];
        }

        usort($roles, function ($a, $b) {
            return strcasecmp($a['title'], $b['title']);
        });
        return $roles;
    }

    public static function retrievePeopleByStatus()
    {
        $statusGroups = self::select('id', 'callsign', 'status')
            ->orderBy('status')
            ->orderBy('callsign')
            ->get()
            ->groupBy('status');

        return $statusGroups->sortKeys()->map(function ($group, $status) {
            return [
                'status' => $status,
                'people' => $group->map(function ($row) {
                    return [
                        'id' => $row->id,
                        'callsign' => $row->callsign
                    ];
                })->values()
            ];
        })->values();
    }

    public static function retrieveRecommendedStatusChanges($year)
    {
        $filterTestAccounts = function ($r) {
            // Filter out testing accounts, and temporary laminates.
            return !preg_match('/(^(testing|lam #|temp \d+))|\(test\)/i', $r->callsign);
        };

        $yearsRangered = DB::raw('(SELECT COUNT(DISTINCT(YEAR(on_duty))) FROM timesheet WHERE person_id=person.id AND position_id NOT IN (1, 13, 29, 30)) AS years');
        $lastYear = DB::raw('(SELECT YEAR(on_duty) FROM timesheet WHERE person_id=person.id ORDER BY on_duty DESC LIMIT 1) AS last_year');

        // Inactive means that you have not rangered in any of the last 3 events
        // but you have rangered in at least one of the last 5 events
        $inactives = Person::select(
            'id',
            'callsign',
            'status',
            'email',
            'vintage',
            $lastYear,
            $yearsRangered
        )->where('status', 'active')
            ->whereRaw('person.id NOT IN (SELECT person_id FROM timesheet WHERE YEAR(on_duty) BETWEEN ? AND ?)', [$year - 3, $year])
            ->whereRaw('person.id IN (SELECT person_id FROM timesheet WHERE YEAR(on_duty) BETWEEN ? AND ?)', [$year - 5, $year - 4])
            ->orderBy('callsign')
            ->get()
            ->filter($filterTestAccounts)->values();

        // Retired means that you have not rangered in any of the last 5 events
        $retired = Person::select(
            'id',
            'callsign',
            'status',
            'email',
            'vintage',
            $lastYear,
            $yearsRangered
        )->whereIn('status', [Person::ACTIVE, Person::INACTIVE, Person::INACTIVE_EXTENSION])
            ->whereRaw('person.id NOT IN (SELECT person_id FROM timesheet WHERE YEAR(on_duty) BETWEEN ? AND ?)', [$year - 5, $year])
            ->orderBy('callsign')
            ->get()
            ->filter($filterTestAccounts)->values();


        // Mark as vintage are people who have been active for 10 years or more.
        $vintage = DB::table('timesheet')
            ->select(
                'person_id as id',
                'callsign',
                'status',
                'email',
                'vintage',
                DB::raw('YEAR(MAX(on_duty)) AS last_year'),
                DB::raw('count(distinct(YEAR(on_duty))) as years')
            )
            ->join('person', 'person.id', 'timesheet.person_id')
            ->whereIn('status', [Person::ACTIVE, Person::INACTIVE])
            ->whereNotIn('position_id', [Position::ALPHA, Position::TRAINING])
            ->where('vintage', false)
            ->groupBy(['person_id', 'callsign', 'status', 'email', 'vintage'])
            ->havingRaw('count(distinct(YEAR(on_duty))) >= 10')
            ->orderBy('callsign')
            ->get()
            ->filter($filterTestAccounts)->values();

        // People who have been active in the last three events yet are listed as inactive
        $actives = Person::select(
            'id',
            'callsign',
            'status',
            'email',
            'vintage',
            $lastYear,
            $yearsRangered
        )->whereIn('status', [Person::INACTIVE, Person::INACTIVE_EXTENSION, Person::RETIRED])
            ->whereRaw('person.id IN (SELECT person_id FROM timesheet WHERE YEAR(on_duty) BETWEEN ? AND ?)', [$year - 3, $year])
            ->orderBy('callsign')
            ->get()
            ->filter($filterTestAccounts)->values();

        $pastProspectives = Person::select('id', 'callsign', 'status', 'email')
            ->whereIn('status', [Person::BONKED, Person::ALPHA, Person::PROSPECTIVE])
            ->orderBy('callsign')
            ->get()
            ->filter($filterTestAccounts)->values();

        return [
            'inactives' => $inactives,
            'retired' => $retired,
            'actives' => $actives,
            'past_prospectives' => $pastProspectives,
            'vintage' => $vintage
        ];
    }

    public function isValidPassword(string $password): bool
    {
        if (self::passwordMatch($this->password, $password)) {
            return true;
        }

        if ($this->tpassword_expire < time()) {
            return false;
        }

        return self::passwordMatch($this->tpassword, $password);
    }

    public static function passwordMatch($encyptedPw, $password): bool
    {
        if (strpos($encyptedPw, ':') === false) {
            return false;
        }

        list($salt, $sha) = explode(':', $encyptedPw);
        $hashedPw = sha1($salt . $password);

        return ($hashedPw == $sha);
    }

    public function changePassword(string $password): bool
    {
        $salt = self::generateRandomString();
        $sha = sha1($salt . $password);

        $this->password = "$salt:$sha";
        $this->tpassword = '';
        $this->tpassword_expire = 0;
        return $this->saveWithoutValidation();
    }

    public function createResetPasswordToken(): string
    {
        $timestamp  = now()->timestamp;
        // Only generate a new token if none exist or the token has expired
        if (empty($this->tpassword)
            || $this->tpassword_expire <= 1
            || ($this->tpassword_expire + self::RESET_PASSWORD_EXPIRE) > $timestamp) {
            $this->tpassword = sprintf("%04x%s", $this->id, self::generateRandomString());
        }
        $this->tpassword_expire = $timestamp + self::RESET_PASSWORD_EXPIRE;
        $this->saveWithoutValidation();

        return $this->tpassword;
    }

    public function getRolesAttribute()
    {
        return $this->roles;
    }

    public function retrieveRoles(): void
    {
        $this->roles = PersonRole::findRoleIdsForPerson($this->id);
    }

    public function hasRole($role): bool
    {
        if ($this->roles === null) {
            $this->retrieveRoles();
        }

        if (is_array($role)) {
            foreach ($role as $r) {
                if (in_array($r, $this->roles)) {
                    return true;
                }
            }
        } else {
            return in_array($role, $this->roles);
        }

        return false;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    public function isPNV(): bool
    {
        $status = $this->status;

        return ($status == Person::PROSPECTIVE || $status == Person::ALPHA);
    }

    public function isAuditor(): bool
    {
        return ($this->status == Person::AUDITOR);
    }

    /*
     * creates a random string by calling random.org, and falls back on a home-rolled.
     * @return the string.
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

    public function getLanguagesAttribute()
    {
        return $this->languages;
    }

    public function setLanguagesAttribute($value)
    {
        $this->languages = $value;
    }

    public function setHasReviewedPiAttribute($value)
    {
        $this->has_reviewed_pi = $value;
    }

    /*
     * Account created prior to 2010 have a 0000-00-00 date. Return null if that's
     * the case
     */

    public function getCreateDateAttribute()
    {
        if ($this->attributes == null) {
            return null;
        }

        $date = $this->attributes['create_date'] ?? null;

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
     * Change the status.
     * TODO figure out a better way to do this.
     *
     */
    public function changeStatus($newStatus, $oldStatus, $reason)
    {
        if ($newStatus == $oldStatus) {
            return;
        }

        $personId = $this->id;
        $this->status_date = now();
        $this->status = $newStatus;

        PersonStatus::record($this->id, $oldStatus, $newStatus, $reason, Auth::id());

        $changeReason = $reason . " new status $newStatus";

        switch ($newStatus) {
            case Person::ACTIVE:
                // grant the new ranger all the basic positions
                $addIds = Position::where('all_rangers', true)->pluck('id');
                PersonPosition::addIdsToPerson($personId, $addIds, $changeReason);

                // Add login role
                $addIds = Role::where('new_user_eligible', true)->pluck('id');
                PersonRole::addIdsToPerson($personId, $addIds, $changeReason);

                // First-year Alphas get the Dirt - Shiny Penny position
                if ($oldStatus == Person::ALPHA) {
                    PersonPosition::addIdsToPerson($personId, [Position::DIRT_SHINY_PENNY], $changeReason);
                }
                break;

            case Person::ALPHA:
                // grant the alpha the alpha position
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

                // Remove asset authorization and lock user out of system
                break;

            case Person::BONKED:
                // Remove all positions
                PersonPosition::resetPositions($personId, $changeReason, Person::REMOVE_ALL);
                break;

            // Note that it used to be that changing status to INACTIVE
            // removed all of your positions other than "Training."  We decided
            // in 2015 not to do this anymore because we lose too much historical
            // information.

            // If you are one of the below, the only role you get is login
            // and position is Training
            case Person::RETIRED:
            case Person::AUDITOR:
            case Person::PROSPECTIVE:
            case Person::PROSPECTIVE_WAITLIST:
            case Person::PAST_PROSPECTIVE:
                // Remove all roles, and reset back to the default roles
                PersonRole::resetRoles($personId, $changeReason, Person::ADD_NEW_USER);

                // Remove all positions, and reset back to the default positions
                PersonPosition::resetPositions($personId, $changeReason, Person::ADD_NEW_USER);
                break;
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
     * If the new callsign already exits, find one that does not exists by appending a number to the last name.
     *
     * e.g. Jane Smith, year 2019 -> SmithJ19
     *           or Smith1J19, Smith2J19, etc if SmithJ19 already exists.
     *
     * @return bool true if the callsign was successfully reset
     */

    public function resetCallsign()
    {
        $year = current_year() % 100;
        for ($tries = 0; $tries < 10; $tries++) {
            $newCallsign = $this->last_name;
            if ($tries > 0) {
                $newCallsign .= $tries + 1;
            }
            $newCallsign .= substr($this->first_name, 0, 1) . $year;
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
     * Normalize store a normalized and metaphone version of the callsign
     */

    public function setCallsignAttribute($value)
    {
        $value = trim($value);
        $this->attributes['callsign'] = $value;
        $this->attributes['callsign_normalized'] = self::normalizeCallsign($value ?? ' ');
        $this->attributes['callsign_soundex'] = metaphone($this->attributes['callsign_normalized']);

        // Update the callsign FKA if the callsign did actually change.
        if ($this->isDirty('callsign')) {
            $oldCallsign = $this->getOriginal('callsign');
            if (!empty($oldCallsign)) {
                $fka = $this->formerly_known_as;
                if (empty($fka)) {
                    $this->formerly_known_as = $oldCallsign;
                } elseif (strpos($fka, $oldCallsign) === false) {
                    $this->formerly_known_as = $fka . ',' . $oldCallsign;
                }
            }
        }
    }

    /**
     * Normalize shirt sizes
     */

    public function getLongsleeveshirtSizeStyleAttribute()
    {
        return empty($this->attributes['longsleeveshirt_size_style']) ? 'Unknown' : $this->attributes['longsleeveshirt_size_style'];
    }

    public function getTeeshirtSizeStyleAttribute()
    {
        return empty($this->attributes['teeshirt_size_style']) ? 'Unknown' : $this->attributes['teeshirt_size_style'];
    }

    /*
     * Summarize gender - used by the Shift Lead Report
     */
    public static function summarizeGender($gender)
    {
        $check = trim(strtolower($gender));

        // Female gender
        if (preg_match('/\b(female|girl|femme|lady|she|her|woman|famale|femal|fem)\b/', $check) || $check == 'f') {
            return 'F';
        }

        // Male gender
        if (preg_match('/\b(male|dude|fella|man|boy)\b/', $check) || $check == 'm') {
            return 'M';
        }

        // Non-Binary
        if (preg_match('/\bnon[\s\-]?binary\b/', $check)) {
            return 'NB';
        }

        // Queer (no gender stated)
        if (preg_match('/\bqueer\b/', $check)) {
            return 'Q';
        }

        // Gender Fluid
        if (preg_match('/\bfluid\b/', $check)) {
            return 'GF';
        }

        // Gender, yes? what does that even mean?
        if ($check == 'yes') {
            return '';
        }

        // Can't determine - return the value
        return $gender;
    }

    public function formerlyKnownAsArray($filter = false)
    {
        return self::splitCommas($this->formerly_known_as, $filter);
    }

    public function knownPnvsArray()
    {
        return self::splitCommas($this->known_pnvs);
    }

    public function knownRangersArray()
    {
        return self::splitCommas($this->known_rangers);
    }

    public function hasReviewedPi()
    {
        return ($this->reviewed_pi_at && $this->reviewed_pi_at->year == current_year());
    }

    public static function splitCommas($str, $filter = false)
    {
        if (empty($str)) {
            return [];
        }

        $names = preg_split('/\s*,\s*/', trim($str));
        if (!$filter) {
            return $names;
        }

        return array_values(array_filter($names, function ($name) {
            return !preg_match('/\d{2,4}[B]?(\(NR\))?$/', $name);
        }));
    }

    public function setPronounsCustomAttribute($value)
    {
        $this->attributes['pronouns_custom'] = !empty($value) ? $value : '';
    }

    public function setPronounsAttribute($value)
    {
        $this->attributes['pronouns'] = !empty($value) ? $value : '';
    }
}

<?php

namespace App\Models;

use App\Attributes\BlankIfEmptyAttribute;
use App\Lib\ClubhouseCache;
use App\Lib\Membership;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Position extends ApiModel
{
    protected $table = 'position';
    protected bool $auditModel = true;

    const int ALPHA = 1;

    const int DIRT = 2;
    const int DIRT_PRE_EVENT = 53;
    const int DIRT_POST_EVENT = 120;
    const int DIRT_SHINY_PENNY = 107;

    const int MENTOR = 9;
    const int MENTOR_LEAD = 15;
    const int MENTOR_SHORT = 35;
    const int MENTOR_APPRENTICE = 70;
    const int MENTOR_MITTEN = 67;
    const int MENTOR_KHAKI = 86;
    const int MENTOR_RADIO_TRAINER = 72;

    const int CHEETAH = 45;
    const int CHEETAH_CUB = 46;

    const int DIRT_GREEN_DOT = 4;
    const int GREEN_DOT_LEAD = 14;
    const int GREEN_DOT_LEAD_INTERN = 126;
    const int GREEN_DOT_LONG = 28;
    const int GREEN_DOT_MENTOR = 40;
    const int GREEN_DOT_MENTEE = 50;
    const int GREEN_DOT_TRAINING = 101;
    const int GREEN_DOT_TRAINER = 100;
    const int SANCTUARY = 54;
    const int SANCTUARY_HOST = 68;
    const int SANCTUARY_MENTEE = 123;

    const int HQ_FULL_TRAINING = 31;
    const int HQ_LEAD = 32;
    const int HQ_LEAD_PRE_EVENT = 118;
    const int HQ_REFRESHER_TRAINING = 49;
    const int HQ_RUNNER = 18;
    const int HQ_SHORT = 33;
    const int HQ_SUPERVISOR = 51;
    const int HQ_TRAINER = 34;
    const int HQ_WINDOW = 3;
    const int HQ_WINDOW_PRE_EVENT = 117;
    const int HQ_WINDOW_SANDMAN = 104;
    const int HQ_TOD = 103;
    const int DOUBLE_OH_7 = 21;
    const int DOUBLE_OH_7_STANDBY = 110;

    const int DPW_RANGER = 105;
    const int NVO_RANGER = 168;

    const int DEEP_FREEZE = 38;

    const int OOD = 10;
    const int DEPUTY_OOD = 87;

    const int ART_SAFETY = 16;
    const int ART_CAR_WRANGLER = 125;


    const int BURN_COMMAND_TEAM = 99;
    const int BURN_PERIMETER = 19;
    const int BURN_QUAD_LEAD = 114;
    const int BURN_JUMP_TEAM = 144;
    const int FIRE_LANE = 143;

    const int SANDMAN = 61;
    const int SANDMAN_TRAINER = 108;
    const int SANDMAN_TRAINING = 80;
    const int SPECIAL_BURN = 27;

    const int SITE_SETUP = 59;
    const int SITE_SETUP_LEAD = 96;
    const int SITE_TEARDOWN = 60;
    const int SITE_TEARDOWN_LEAD = 97;

    const int RSC_SHIFT_LEAD = 12;
    const int RSC_SHIFT_LEAD_PRE_EVENT = 83;

    const int OPERATIONS_MANAGER = 37;

    const int DEPARTMENT_MANAGER = 106;

    const int TROUBLESHOOTER = 91;
    const int TROUBLESHOOTER_MENTEE = 127;
    const int TROUBLESHOOTER_MENTOR = 128;
    const int TROUBLESHOOTER_TRAINER = 129;
    const int TROUBLESHOOTER_TRAINING = 94;
    const int TROUBLESHOOTER_LEAL = 147;
    const int TROUBLESHOOTER_LEAL_PRE_EVENT = 148;
    const int TROUBLESHOOTER_PRE_EVENT = 149;

    // Trainer positions for Dirt
    const int TRAINING = 13;
    const int TRAINER = 23;
    const int TRAINER_ASSOCIATE = 88;
    const int TRAINER_UBER = 95;

    const int TOW_TRUCK_DRIVER = 20;
    const int TOW_TRUCK_MENTEE = 69;
    const int TOW_TRUCK_TRAINING = 102;
    const int TOW_TRUCK_TRAINER = 121;

    const int TECH_ON_CALL = 113;
    const int TECH_OPS = 24;

    const int GERLACH_PATROL = 75;
    const int GERLACH_PATROL_LEAD = 76;
    const int GERLACH_PATROL_GREEN_DOT = 122;
    const int GERLACH_PATROL_LONG = 145;

    const int HOT_SPRINGS_PATROL_DRIVER = 39;

    const int ECHELON_FIELD = 17;
    const int ECHELON_FIELD_LEAD = 81;
    const int ECHELON_FIELD_LEAD_TRAINING = 111;

    const int LNT_OUTREACH = 73;

    const int RSCI = 25;
    const int RSCI_MENTOR = 92;
    const int RSCI_MENTEE = 93;
    const int RSC_DISPATCH = 71;
    const int RSC_RESIDENT = 77;
    const int RSC_SHIFT_COORD = 78;
    const int RSC_WESL = 109;
    const int RSCI_FB_CONSELOR = 64;


    const int RNR = 11;
    const int RNR_RIDE_ALONG = 115;

    const int PERSONNEL_MANAGER = 63;
    const int PERSONNEL_INVESTIGATOR = 82;

    const int INTERCEPT = 5;
    const int INTERCEPT_DISPATCH = 6;
    const int INTERCEPT_OPERATOR = 66;

    const int OPERATOR = 56;
    const int OPERATOR_SMOOTH = 142;

    const int LEAL = 7;
    const int LEAL_MENTEE = 8;
    const int LEAL_MENTOR = 85;
    const int LEAL_PARTNER = 116;
    const int LEAL_PRE_EVENT = 119;

    const int QUARTERMASTER = 84;
    const int QUARTERMASTER_LEAD = 146;

    const int VEHICLE_MAINTENANCE_LEAD = 124;
    const int VEHICLE_MAINTENANCE = 74;

    const int VOLUNTEER_COORDINATOR = 112;

    const int IMS_ADMIN = 89;
    const int IMS_AUDIT = 90;
    const int IMS_TRAINING = 62;

    const int LOGISTICS_LEAD = 98;
    const int LOGISTICS = 42;
    const int LOGISTICS_MANAGER = 151;

    const int REGIONAL_RANGER_NETWORK = 154;

    /*
     * Position types
     */

    const string TYPE_COMMAND = 'Command';
    const string TYPE_FRONTLINE = 'Frontline';
    const string TYPE_HQ = 'HQ';
    const string TYPE_LOGISTICS = 'Logistics';
    const string TYPE_MENTORING = 'Mentoring';
    const string TYPE_OTHER = 'Other';
    const string TYPE_TRAINING = 'Training';

    const array TYPES = [
        self::TYPE_COMMAND,
        self::TYPE_FRONTLINE,
        self::TYPE_HQ,
        self::TYPE_LOGISTICS,
        self::TYPE_MENTORING,
        self::TYPE_OTHER,
        self::TYPE_TRAINING,
    ];

    //
    // List of training positions with their associated trainers
    // TODO: create a join table to encapsulate this so this is not hard coded.
    //

    const array TRAINERS = [
        Position::TRAINING => [
            Position::TRAINER,
            Position::TRAINER_ASSOCIATE,
            Position::TRAINER_UBER
        ],
        Position::GREEN_DOT_TRAINING => [Position::GREEN_DOT_TRAINER],
        Position::HQ_FULL_TRAINING => [Position::HQ_TRAINER],
        Position::HQ_REFRESHER_TRAINING => [Position::HQ_TRAINER],
        Position::SANDMAN_TRAINING => [Position::SANDMAN_TRAINER],
        Position::TOW_TRUCK_TRAINING => [Position::TOW_TRUCK_TRAINER],
    ];


    const array PROBLEM_HOURS = [
        Position::OOD => 28,
        Position::PERSONNEL_MANAGER => 28,
        Position::GREEN_DOT_LEAD => 28,
        Position::HQ_LEAD => 28,
        Position::TOW_TRUCK_DRIVER => 28,

        Position::MENTOR_SHORT => 17,

        Position::GERLACH_PATROL => 15,
        Position::GERLACH_PATROL_LEAD => 15,
        Position::DEPUTY_OOD => 15,

        Position::HOT_SPRINGS_PATROL_DRIVER => 15,
    ];

    const array HQ_WORKERS = [
        Position::HQ_LEAD,
        Position::HQ_LEAD_PRE_EVENT,
        Position::HQ_SHORT,
        Position::HQ_WINDOW,
        Position::HQ_WINDOW_PRE_EVENT,
        Position::HQ_SUPERVISOR,
    ];

    // Person has not signed the Sandman affidavit (all wanna be Sandmen)
    const string UNQUALIFIED_UNSIGNED_SANDMAN_AFFIDAVIT = 'unsigned-sandman-affidavit';
    // Person has no Burn Perimeter experience (all wanna be Sandmen)
    const string UNQUALIFIED_NO_BURN_PERIMETER_EXP = 'no-burn-perimeter-exp';
    // Person has not completed dirt training or ART as required by the position
    const string UNQUALIFIED_UNTRAINED = 'untrained';

    const array UNQUALIFIED_MESSAGES = [
        self::UNQUALIFIED_UNSIGNED_SANDMAN_AFFIDAVIT => 'Sandman Affidavit not signed',
        self::UNQUALIFIED_NO_BURN_PERIMETER_EXP => 'No Burn Perimeter, nor Sandman, shift has been worked within the last ' . self::SANDMAN_YEAR_CUTOFF . ' years',
        self::UNQUALIFIED_UNTRAINED => 'Training not completed.',
    ];

    const array SANDMAN_QUALIFIED_POSITIONS = [
        Position::BURN_COMMAND_TEAM,
        Position::BURN_PERIMETER,
        Position::BURN_QUAD_LEAD,
        Position::SANDMAN,
        Position::SPECIAL_BURN
    ];

    const int SANDMAN_YEAR_CUTOFF = 5;

    const array ART_GRADUATE_TO_POSITIONS = [
        self::TOW_TRUCK_TRAINING => [
            'veteran' => self::TOW_TRUCK_DRIVER,
            'positions' => [self::TOW_TRUCK_MENTEE],
        ],

        self::SANDMAN_TRAINING => [
            'positions' => [self::SANDMAN],
        ],

        self::GREEN_DOT_TRAINING => [
            'veteran' => self::DIRT_GREEN_DOT,
            'positions' => [self::GREEN_DOT_MENTEE, self::SANCTUARY_MENTEE]
        ],
    ];

    const string TEAM_CATEGORY_ALL_MEMBERS = 'all_members';
    const string TEAM_CATEGORY_OPTIONAL = 'optional';
    const string TEAM_CATEGORY_PUBLIC = 'public';

    protected $fillable = [
        'active',
        'auto_sign_out',
        'alert_when_no_trainers',
        'alert_when_becomes_empty',
        'all_rangers',
        'contact_email',
        'count_hours',
        'deselect_on_team_join',
        'max',
        'min',
        'mvr_eligible',
        'new_user_eligible',
        'no_payroll_hours_adjustment',
        'no_training_required',
        'on_sl_report',
        'on_trainer_report',
        'paycode',
        'prevent_multiple_enrollments',
        'pvr_eligible',
        'require_training_for_roles',
        'resource_tag',
        'role_ids',
        'short_title',
        'sign_out_hour_cap',
        'team_category',
        'team_id',
        'title',
        'training_position_id',
        'type',
    ];

    protected $casts = [
        'active' => 'bool',
        'alert_when_becomes_empty' => 'bool',
        'alert_when_no_trainers' => 'bool',
        'all_rangers' => 'bool',
        'auto_sign_out' => 'bool',
        'deselect_on_team_join' => 'bool',
        'mvr_eligible' => 'bool',
        'new_user_eligible' => 'bool',
        'no_payroll_hours_adjustment' => 'bool',
        'no_training_required' => 'bool',
        'on_sl_report' => 'bool',
        'on_trainer_report' => 'bool',
        'prevent_multiple_enrollments' => 'bool',
        'pvr_eligible' => 'bool',
        'require_training_for_roles' => 'bool',
        'sign_out_hour_cap' => 'float',
    ];

    protected $rules = [
        'title' => 'required|string|max:40',
        'short_title' => 'sometimes|string|max:6|nullable',
        'min' => 'integer',
        'max' => 'integer',
        'training_position_id' => 'nullable|exists:position,id',
        'team_id' => 'nullable|exists:team,id',
        'role_ids' => 'sometimes|array',
        'role_ids.*' => 'sometimes|integer|exists:role,id'
    ];

    protected $hidden = [
        'position_roles',
        'roles',
    ];

    // Pseudo column - for the position role grants
    public array|null $role_ids = null;

    public static function boot(): void
    {
        parent::boot();

        self::saved(function ($model) {
            if (is_array($model->role_ids)) {
                // Update Position Roles
                Membership::updatePositionRoles($model->id, $model->role_ids, '');
            }
        });

        self::deleted(function ($model) {
            DB::table('position_role')->where('position_id', $model->id)->delete();
            DB::table('person_position')->where('position_id', $model->id)->delete();
            ClubhouseCache::flush();
        });
    }

    /**
     * Validate if require_training_for_roles is set, then  training_position_id has to be set as well.
     *
     * @param $options
     * @return bool
     * @throws ValidationException
     */

    public function save($options = []): bool
    {
        if ($this->require_training_for_roles && !$this->training_position_id) {
            $this->addError('require_training_for_roles', 'Required training for roles set but no training position was given');
            return false;
        }

        if ($this->no_training_required && $this->training_position_id) {
            $this->addError('no_training_required', 'Training position and no training required flag cannot be set together.');
            return false;
        }

        return parent::save($options);
    }

    public function training_positions(): HasMany
    {
        return $this->hasMany(Position::class, 'training_position_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function position_roles(): HasMany
    {
        return $this->hasMany(PositionRole::class, 'position_id');
    }

    public function team_positions(): HasMany
    {
        return $this->hasMany(Position::class, 'team_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'position_role');
    }

    public function members(): HasManyThrough
    {
        return $this->hasManyThrough(Person::class, PersonPosition::class, 'position_id', 'id', 'id', 'person_id')->orderBy('person.callsign');
    }

    /**
     * Find  positions based on criteria
     *
     * @param array $query
     * @param bool $isAdmin
     * @return Collection
     */

    public static function findForQuery(array $query, bool $isAdmin = false): Collection
    {
        $type = $query['type'] ?? null;
        $includeRoles = $query['include_roles'] ?? false;
        $hasPaycode = $query['has_paycode'] ?? false;
        $canManage = $query['can_manage'] ?? false;

        $sql = self::select('position.*')->orderBy('title');

        if ($canManage && !$isAdmin) {
            $sql->leftJoin('team_manager', function ($j)  {
                $j->on('team_manager.team_id', 'position.team_id');
                $j->where('team_manager.person_id', Auth::id());
            })->addSelect(DB::raw('IF(team_manager.team_id is null, false, true) AS can_manage'));
        }

        if (!empty($type)) {
            $sql->where('type', $type);
        }

        if ($hasPaycode) {
            $sql->where('paycode', '!=', '');
            $sql->whereNotNull('paycode');
        }

        if (isset($params['active'])) {
            $sql->where('active', $params['active']);
        }

        $rows = $sql->get();

        if ($canManage && $isAdmin) {
            foreach ($rows as $row) {
                $row->can_manage = true;
            }
        }

        if ($includeRoles) {
            $rows->load('position_roles');
            foreach ($rows as $row) {
                $row->loadRoles();
            }
        }

        return $rows;
    }

    /**
     * Load up the position roles.
     *
     * @return void
     */

    public function loadRoles(): void
    {
        $this->role_ids = $this->position_roles->pluck('role_id')->toArray();
        $this->append('role_ids');
    }

    /**
     * Find by title
     *
     * @param string $title
     * @return Position|null
     */

    public static function findByTitle(string $title): ?Position
    {
        return self::where('title', $title)->first();
    }

    /**
     * Find all training positions that are not trainer's training positions.
     * Optionally exclude Dirt Training (for ART module support)
     * Return only the id & title.
     *
     * @param bool $excludeDirt
     * @return array
     */

    public static function findAllTrainings(bool $excludeDirt = false): array
    {
        $sql = self::select('id', 'title')
            ->where('type', Position::TYPE_TRAINING)
            ->where('title', 'not like', '%trainer%')
            ->where('active', true)
            ->orderBy('title');

        if ($excludeDirt) {
            $sql = $sql->where('id', '!=', Position::TRAINING);
        }

        return $sql->get()->toArray();
    }

    /**
     * Retrieve the title for a position. Return a position id if the
     * position was not found.
     * @param int $id
     * @return string
     */

    public static function retrieveTitle(int $id): string
    {
        $row = DB::table('position')->select('title')->where('id', $id)->first();

        return $row ? $row->title : "Position #{$id}";
    }

    /**
     * Find all positions which reference the given training position
     * @param int $positionId
     * @return Collection
     */

    public static function findTrainedPositions(int $positionId): Collection
    {
        return self::select('id', 'title')->where('training_position_id', $positionId)->get();
    }

    /**
     * Find all positions with working (started) slots
     */

    public static function findAllWithInProgressSlots(bool $ctmOnly = true): \Illuminate\Support\Collection
    {
        $now = (string)now();
        $sql = self::where('type', '!=', self::TYPE_TRAINING)
            ->whereRaw('EXISTS (SELECT 1 FROM slot WHERE slot.active IS TRUE
                AND slot.position_id=position.id
                AND ? >= DATE_SUB(slot.begins, INTERVAL 6 HOUR)
                AND ? < slot.ends LIMIT 1)', [$now, $now])
            ->where('position.active', true)
            ->orderBy('position.title')
            ->with('team:id,title');

        if ($ctmOnly) {
            $teams = TeamManager::findForPerson(Auth::id());
            if ($teams->isEmpty()) {
                return collect([]);
            }

            $sql->whereIn('team_id', $teams->pluck('team_id'));
        }

        return $sql->get();
    }

    /**
     * Find all active positions and optionally filtered if the person is a CTM for the team positions.
     * @param bool $ctmOnly
     * @return \Illuminate\Support\Collection
     */

    public static function retrieveAllActive(bool $ctmOnly = true): \Illuminate\Support\Collection
    {
        $sql = self::where('active', true)->with('team:id,title');
        if ($ctmOnly) {
            $teams = TeamManager::findForPerson(Auth::id());
            if ($teams->isEmpty()) {
                return collect([]);
            }

            $sql->whereIn('team_id', $teams->pluck('team_id'));
        }

        return $sql->orderBy('position.title')->get();
    }

    /**
     * Is the person qualified to work a Sandman position?
     *
     * @param Person $person
     * @param $reason
     * @return bool true if the person is qualified
     */

    public static function isSandmanQualified(Person $person, &$reason): bool
    {
        if (setting('SandmanRequireAffidavit')) {
            $event = PersonEvent::findForPersonYear($person->id, current_year());
            if (!$event || !$event->sandman_affidavit) {
                $reason = self::UNQUALIFIED_UNSIGNED_SANDMAN_AFFIDAVIT;
                return false;
            }
        }

        if (setting('SandmanRequirePerimeterExperience')) {
            if (!Timesheet::didPersonWorkPosition($person->id, self::SANDMAN_YEAR_CUTOFF, self::SANDMAN_QUALIFIED_POSITIONS)) {
                $reason = self::UNQUALIFIED_NO_BURN_PERIMETER_EXP;
                return false;
            }
        }

        return true;
    }

    /**
     * Return the "sub type" of the position - i.e. return an additional types
     * For mentoring positions, figure out if it's a mentor or mentee position (Alpha & Cheetah Cub)
     *
     * @return string|null
     */

    public function getSubtypeAttribute(): ?string
    {
        $id = $this->id;

        if ($this->type == self::TYPE_MENTORING) {
            if ($id == Position::ALPHA || $id == Position::CHEETAH_CUB) {
                return 'mentee';
            }
            return 'mentor';
        } else {
            return $this->type;
        }
    }

    /**
     * Get the pseudo role_ids field
     *
     * @return array|null
     */

    public function getRoleIdsAttribute(): ?array
    {
        return $this->role_ids;
    }

    /**
     * Set the pseudo role_ids field
     *
     * @param $value
     * @return void
     */

    public function setRoleIdsAttribute($value): void
    {
        $this->role_ids = $value;
    }

    /**
     * Set the paycode to null if empty
     *
     * @return Attribute
     */

    public function paycode(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    /**
     * Set the resource tag (aka document tag)
     *
     * @return Attribute
     */

    public function resourceTag(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends ApiModel
{
    protected $table = 'position';
    protected $auditModel = true;

    const ALPHA = 1;

    const DIRT = 2;
    const DIRT_PRE_EVENT = 53;
    const DIRT_POST_EVENT = 120;
    const DIRT_SHINY_PENNY = 107;

    const MENTOR = 9;
    const MENTOR_LEAD = 15;
    const MENTOR_SHORT = 35;
    const MENTOR_APPRENTICE = 70;
    const MENTOR_MITTEN = 67;
    const MENTOR_KHAKI = 86;
    const MENTOR_RADIO_TRAINER = 72;

    const CHEETAH = 45;
    const CHEETAH_CUB = 46;

    const DIRT_GREEN_DOT = 4;
    const GREEN_DOT_LEAD = 14;
    const GREEN_DOT_LEAD_INTERN = 126;
    const GREEN_DOT_MENTOR = 40;
    const GREEN_DOT_MENTEE = 50;
    const GREEN_DOT_TRAINING = 101;
    const GREEN_DOT_TRAINER = 100;
    const SANCTUARY = 54;
    const SANCTUARY_HOST = 68;
    const SANCTUARY_MENTEE = 123;

    const HQ_FULL_TRAINING = 31;
    const HQ_LEAD = 32;
    const HQ_LEAD_PRE_EVENT = 118;
    const HQ_REFRESHER_TRAINING = 49;
    const HQ_RUNNER = 18;
    const HQ_SHORT = 33;
    const HQ_SUPERVISOR = 51;
    const HQ_TRAINER = 34;
    const HQ_WINDOW = 3;
    const HQ_WINDOW_PRE_EVENT = 117;

    const DOUBLE_OH_7 = 21;

    const DPW_RANGER = 105;

    const OOD = 10;
    const DEPUTY_OOD = 87;

    const ART_CAR_WRANGLER = 125;
    const BURN_COMMAND_TEAM = 99;
    const BURN_PERIMETER = 19;
    const BURN_QUAD_LEAD = 114;
    const SANDMAN = 61;
    const SANDMAN_TRAINER = 108;
    const SANDMAN_TRAINING = 80;
    const SPECIAL_BURN = 27;

    const SITE_SETUP = 59;
    const SITE_SETUP_LEAD = 96;

    const RSC_SHIFT_LEAD = 12;
    const RSC_SHIFT_LEAD_PRE_EVENT = 83;

    const OPERATIONS_MANAGER = 37;
    const DEPARTMENT_MANAGER = 106;

    const TROUBLESHOOTER = 91;
    const TROUBLESHOOTER_MENTEE = 127;

    // Trainer positions for Dirt
    const TRAINING = 13;
    const TRAINER = 23;
    const TRAINER_ASSOCIATE = 88;
    const TRAINER_UBER = 95;

    const TOW_TRUCK_DRIVER = 20;
    const TOW_TRUCK_MENTEE = 69;
    const TOW_TRUCK_TRAINING = 102;
    const TOW_TRUCK_TRAINER = 121;

    const TECH_ON_CALL = 113;
    const TECH_TEAM = 24;

    const GERLACH_PATROL = 75;
    const GERLACH_PATROL_LEAD = 76;
    const GERLACH_PATROL_GREEN_DOT = 122;

    const HOT_SPRINGS_PATROL_DRIVER = 39;

    const ECHELON_FIELD = 17;
    const ECHELON_FIELD_LEAD = 81;
    const ECHELON_FIELD_LEAD_TRAINING = 111;

    const RSCI = 25;
    const RSCI_MENTOR = 92;
    const RSCI_MENTEE = 93;

    const RNR = 11;
    const RNR_RIDE_ALONG = 115;

    const PERSONNEL_MANAGER = 63;
    const PERSONNEL_INVESTIGATOR = 82;

    const INTERCEPT = 5;
    const INTERCEPT_DISPATCH = 6;
    const INTERCEPT_OPERATOR = 66;

    const OPERATOR = 56;
    const RSC_WESL = 109;

    const LEAL = 7;
    const LEAL_MENTEE = 8;
    const LEAL_MENTOR = 85;
    const LEAL_PARTNER = 116;
    const LEAL_PRE_EVENT = 119;

    const QUARTERMASTER = 84;

    const DEEP_FREEZE = 38;

    /*
     * Position types
     */
    const TYPE_COMMAND = 'Command';
    const TYPE_FRONTLINE = 'Frontline';
    const TYPE_HQ = 'HQ';
    const TYPE_LOGISTICS = 'Logistics';
    const TYPE_MENTORING = 'Mentoring';
    const TYPE_OTHER = 'Other';
    const TYPE_TRAINING = 'Training';

    const TYPES = [
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

    const TRAINERS = [
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


    const PROBLEM_HOURS = [
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

    const HQ_WORKERS = [
        Position::HQ_LEAD,
        Position::HQ_LEAD_PRE_EVENT,
        Position::HQ_SHORT,
        Position::HQ_WINDOW,
        Position::HQ_WINDOW_PRE_EVENT,
        Position::HQ_SUPERVISOR,
    ];

    // Person has not signed the Sandman affidavit (all wanna be Sandmen)
    const UNQUALIFIED_UNSIGNED_SANDMAN_AFFIDAVIT = 'unsigned-sandman-affidavit';
    // Person has no Burn Perimeter experience (all wanna be Sandmen)
    const UNQUALIFIED_NO_BURN_PERIMETER_EXP = 'no-burn-perimeter-exp';
    // Person has not completed dirt training or ART as required by the position
    const UNQUALIFIED_UNTRAINED = 'untrained';

    const UNQUALIFIED_MESSAGES = [
        self::UNQUALIFIED_UNSIGNED_SANDMAN_AFFIDAVIT => 'Sandman Affidavit not signed',
        self::UNQUALIFIED_NO_BURN_PERIMETER_EXP => 'No Burn Perimeter experience',
        self::UNQUALIFIED_UNTRAINED => 'Not trained',
    ];

    const SANDMAN_QUALIFIED_POSITIONS = [
        Position::BURN_COMMAND_TEAM,
        Position::BURN_PERIMETER,
        Position::BURN_QUAD_LEAD,
        Position::SANDMAN,
        Position::SPECIAL_BURN
    ];

    const SANDMAN_YEAR_CUTOFF = 5;

    protected $fillable = [
        'active',
        'alert_when_empty',
        'all_rangers',
        'contact_email',
        'count_hours',
        'max',
        'min',
        'new_user_eligible',
        'on_sl_report',
        'prevent_multiple_enrollments',
        'short_title',
        'title',
        'training_position_id',
        'type',
    ];

    protected $casts = [
        'all_rangers' => 'bool',
        'new_user_eligible' => 'bool',
        'on_sl_report' => 'bool',
        'prevent_multiple_enrollments' => 'bool',
        'active' => 'bool',
        'alert_when_empty' => 'bool',
    ];

    protected $rules = [
        'title' => 'required|string|max:40',
        'short_title' => 'sometimes|string|max:6|nullable',
        'min' => 'integer',
        'max' => 'integer',
        'training_position_id' => 'nullable|exists:position,id'
    ];

    public function training_positions(): HasMany
    {
        return $this->hasMany(Position::class, 'training_position_id');
    }

    /**
     * Find  positions based on criteria
     * @param array $query
     * @return Collection
     */

    public static function findForQuery(array $query): Collection
    {
        $type = $query['type'] ?? null;

        $sql = self::orderBy('title');

        if (!empty($type)) {
            $sql->where('type', $type);
        }

        return $sql->get();
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
        $row = self::find($id);

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
     * @return Collection
     */

    public static function findAllWithInProgressSlots(): Collection
    {
        $now = (string)now();
        return self::where('type', '!=', self::TYPE_TRAINING)
            ->whereRaw('EXISTS (SELECT 1 FROM slot WHERE slot.active IS TRUE
                AND slot.position_id=position.id
                AND ? >= DATE_SUB(slot.begins, INTERVAL 6 HOUR)
                AND ? < slot.ends LIMIT 1)', [$now, $now])
            ->orderBy("title")
            ->get();
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
     * @return string
     */

    public function getSubtypeAttribute(): string
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
}

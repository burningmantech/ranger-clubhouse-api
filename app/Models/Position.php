<?php

namespace App\Models;

use App\Models\ApiModel;

use Illuminate\Support\Facades\DB;

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

    const HQ_WINDOW = 3;
    const HQ_WINDOW_PRE_EVENT = 117;
    const HQ_TRAINER = 34;
    const HQ_FULL_TRAINING = 31;
    const HQ_REFRESHER_TRAINING = 49;
    const HQ_LEAD = 32;
    const HQ_LEAD_PRE_EVENT = 118;
    const HQ_SHORT = 33;
    const HQ_RUNNER = 18;

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

    const TYPES = [
        'Command',
        'Frontline',
        'HQ',
        'Logistics',
        'Mentoring',
        'Other',
        'Training',
    ];

    //
    // List of training positions with their associated trainers
    // TODO: create a join table to enclapse this so this is not hard coded.
    //

    const TRAINERS = [
        Position::TRAINING => [
             Position::TRAINER,
             Position::TRAINER_ASSOCIATE,
             Position::TRAINER_UBER
        ],
        Position::GREEN_DOT_TRAINING => [ Position::GREEN_DOT_TRAINER ],
        Position::HQ_FULL_TRAINING => [ Position::HQ_TRAINER ],
        Position::HQ_REFRESHER_TRAINING => [ Position::HQ_TRAINER ],
        Position::SANDMAN_TRAINING => [ Position::SANDMAN_TRAINER ],
        Position::TOW_TRUCK_TRAINING => [Position::TOW_TRUCK_TRAINER ],
    ];


    /*
     * To qualify to work a Sandman position the person must have worked
     * one of the following positions within the last SANDMAN_YEAR_CUTOFF years.
     */

    const SANDMAN_QUALIFIED_POSITIONS = [
        Position::BURN_COMMAND_TEAM,
        Position::BURN_PERIMETER,
        Position::BURN_QUAD_LEAD,
        Position::SANDMAN,
        Position::SPECIAL_BURN
    ];

    const SANDMAN_YEAR_CUTOFF = 5;

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


    protected $fillable = [
        'all_rangers',
        'auto_signout',
        'count_hours',
        'max',
        'min',
        'new_user_eligible',
        'on_sl_report',
        'prevent_multiple_enrollments',
        'short_title',
        'contact_email',
        'title',
        'training_position_id',
        'type',
    ];

    protected $casts = [
        'all_rangers'       => 'bool',
        'auto_signout'      => 'bool',
        'new_user_eligible' => 'bool',
        'on_sl_report'      => 'bool',
        'prevent_multiple_enrollments' => 'bool'
    ];

    protected $rules = [
        'title' => 'required|string|max:40',
        'short_title' => 'sometimes|string|max:6|nullable',
        'min'   => 'integer',
        'max'   => 'integer',
        'training_position_id'  => 'nullable|exists:position,id'
    ];

    public function training_positions()
    {
        return $this->hasMany(Position::class, 'training_position_id');
    }


     /**
     * Find  positions based on criteria
     * @param $query
     * @return Position[]|\Illuminate\Database\Eloquent\Collection
     */
    public static function findForQuery($query)
    {
        $type = $query['type'] ?? null;

        $sql = self::orderBy('title');

        if (!empty($type)) {
            $sql->where('type', $type);
        }

        return $sql->get();
    }

    /*
     * Find all training positions that are not trainer's training positions.
     * Optionally exclude Dirt Training (for ART module support)
     * Return only the id & title.
     */

    public static function findAllTrainings($excludeDirt = false)
    {
        $sql = self::select('id', 'title')
            ->where('type', '=', 'Training')
            ->where('title', 'not like', '%trainer%')
            ->orderBy('title');

        if ($excludeDirt) {
            $sql = $sql->where('id', '!=', Position::TRAINING);
        }

        return $sql->get()->toArray();
    }

    /*
     * Retrieve the title for a position. Return a position id if the
     * position was not found.
     */

    public static function retrieveTitle($id)
    {
        $row = self::find($id);

        if ($row == null) {
            return "Position #{$id}";
        } else {
            return $row->title;
        }
    }

    /*
     * Find all positions which reference the given training position
     *
     * @param int $positionId training position
     * @return array positions which reference the training position.
     */

    public static function findTrainedPositions($positionId)
    {
        return self::select('id', 'title')->where('training_position_id', $positionId)->get();
    }

    /*
     * Find all positions with working (started) slots
     */

    public static function findAllWithInProgressSlots()
    {
        return self::where('type', '!=', 'Training')
                ->whereRaw("EXISTS (SELECT 1 FROM slot WHERE slot.active IS TRUE
                AND slot.position_id=position.id
                AND NOW() >= DATE_SUB(slot.begins, INTERVAL 6 HOUR)
                AND NOW() < slot.ends LIMIT 1)")
                ->orderBy("title")
                ->get();
    }

    /*
     * Is the person qualified for the Sandpeople?
     */

    public static function isSandmanQualified($person, & $reason)
    {
        if (!$person->sandman_affidavit) {
            $reason = 'Sandman affidavit not signed';
            return false;
        }

        if (!Timesheet::didPersonWorkPosition($person->id, self::SANDMAN_YEAR_CUTOFF, self::SANDMAN_QUALIFIED_POSITIONS)) {
            $reason = 'No Burn Perimeter exp.';
            return false;
        }

        return true;
    }

    /*
     * Find everyone who is a Sandman and report on their eligibility.
     */

    public static function retrieveSandPeopleQualifications()
    {
        $year = current_year();
        $cutoff =  $year - self::SANDMAN_YEAR_CUTOFF;

        $positionIds = implode(',', self::SANDMAN_QUALIFIED_POSITIONS);

        $sandPeople = DB::table('person')
                    ->select(
                        'id',
                        'callsign',
                        'sandman_affidavit',
                        DB::raw("EXISTS (SELECT 1 FROM timesheet WHERE timesheet.person_id=person.id AND YEAR(on_duty) >= $cutoff AND position_id IN ($positionIds) LIMIT 1) AS has_experience"),
                        DB::raw("EXISTS (SELECT 1 FROM trainee_status JOIN slot ON slot.id=trainee_status.slot_id WHERE trainee_status.person_id=person.id AND slot.position_id=".Position::SANDMAN_TRAINING." AND YEAR(slot.begins)=$year AND passed=1 LIMIT 1) as is_trained"),
                        DB::raw("EXISTS (SELECT 1 FROM person_slot JOIN slot ON slot.id=person_slot.slot_id WHERE person_slot.person_id=person.id AND slot.position_id=".Position::SANDMAN." AND YEAR(slot.begins)=$year LIMIT 1) as is_signed_up")
                     )
                    ->where('status', 'active')
                    ->whereRaw('EXISTS (SELECT 1 FROM person_position WHERE person_position.person_id=person.id AND person_position.position_id=?)', [ Position::SANDMAN ])
                    ->orderBy('callsign')
                    ->get();

        return [
            'sandpeople' => $sandPeople,
            'cutoff_year' => $cutoff
        ];
    }

    /*
     * Report on problematic position assignments and roles
     *
     * "STOP THE INSANITY" -- Susan Powders, 1990s self proclaimed exercise "guru"
     *  & peroxide enthusiast.
     */

    public static function sanityChecker() : array
    {
        $year = current_year();

        $insanity['green_dot'] = DB::select(
            "SELECT * FROM (SELECT p.id AS id, callsign, status, " .
            "EXISTS (SELECT 1 FROM person_position WHERE person_id = p.id AND position_id = ?) AS has_dirt_green_dot, " .
            "EXISTS (SELECT 1 FROM person_position WHERE person_id = p.id AND position_id = ?) AS has_sanctuary, " .
            "EXISTS (SELECT 1 FROM person_position WHERE person_id = p.id AND position_id = ?) AS has_gp_gd " .
            "FROM person p) t1 WHERE has_dirt_green_dot != has_sanctuary OR has_dirt_green_dot != has_gp_gd ORDER BY callsign",
            [ Position::DIRT_GREEN_DOT, Position::SANCTUARY, Position::GERLACH_PATROL_GREEN_DOT ]
        );

        // All HQ, Operators, Shift Leads, etc. should have the "Management Mode" role
        $positionIds = [
            Position::GREEN_DOT_LEAD_INTERN,
            Position::GREEN_DOT_LEAD,
            Position::HQ_LEAD,
            Position::HQ_SHORT,
            Position::HQ_WINDOW,
            Position::INTERCEPT_DISPATCH,
            Position::MENTOR_LEAD,
            Position::OPERATOR,
            Position::PERSONNEL_INVESTIGATOR,
            Position::QUARTERMASTER,
            Position::RSC_SHIFT_LEAD_PRE_EVENT,
            Position::RSC_SHIFT_LEAD,
            Position::RSC_WESL,
            Position::RSCI_MENTEE,
            Position::RSCI,
            Position::TECH_ON_CALL,
            Position::TRAINER,
        ];

        $personIds = PersonPosition::whereIn('position_id', $positionIds)
                    ->groupBy('person_id')
                    ->pluck('person_id');

        $rows = Person::select('id', 'callsign', 'status', DB::raw("EXISTS (SELECT 1 FROM timesheet WHERE YEAR(on_duty)=$year AND person_id=person.id AND position_id=".Position::ALPHA." LIMIT 1) AS is_shiny_penny"))
                    ->whereIn('id', $personIds)
                    ->whereIn('status', [ Person::ACTIVE, Person::INACTIVE, Person::INACTIVE_EXTENSION ])
                    ->whereRaw('NOT EXISTS (SELECT 1 FROM person_role WHERE person_role.person_id=person.id AND person_role.role_id=?)', [ Role::MANAGE ])
                    ->orderBy('callsign')
                    ->with([ 'person_position' => function ($q) use ($positionIds) {
                        $q->whereIn('position_id', $positionIds);
                    }])
                    ->get();

        $insanity['management_role'] = [];
        foreach ($rows as $row) {
            $positions = Position::select('id', 'title')->whereIn('id', $row->person_position->pluck('position_id'))->orderBy('title')->get();

            $insanity['management_role'][] = [
                'id'             => $row->id,
                'callsign'       => $row->callsign,
                'status'         => $row->status,
                'is_shiny_penny' => $row->is_shiny_penny,
                'positions'      => $positions
            ];
        }

        $insanity['shiny_pennies'] = DB::select(
            "SELECT * FROM (SELECT p.id AS id, callsign, status, year, " .
                        "EXISTS(SELECT 1 FROM person_position WHERE person_id = p.id AND position_id = ?) AS has_shiny_penny " .
                        "FROM person p INNER JOIN " .
                        "(SELECT person_id, MAX(mentor_year) as year FROM person_mentor " .
                        "  WHERE status = 'pass' GROUP BY person_id) pm " .
                        "ON pm.person_id = p.id) t1 ".
                        "WHERE (NOT has_shiny_penny AND year = $year) OR (has_shiny_penny AND year != $year) ".
                        "ORDER BY year desc, callsign",
                        [ Position::DIRT_SHINY_PENNY ]
        );

        $insanity['shiny_penny_year'] = $year;

        return $insanity;
    }

    /**
     * Repair position / role problems
     *
     * @param string $repair - the name of the thing to repair 'green-dot', 'management-role', 'shiny-penny'
     * @param array $peopleIds - list of person ids to repair. Ids are assumed to exist.
     * @return array
     */

    public static function repair(string $repair, array $peopleIds) : array
    {
        $results = [];

        switch ($repair) {
        case 'green-dot':
            foreach ($peopleIds as $personId) {
                $messages = [];
                $errors = [];

                $hasDirt = PersonPosition::havePosition($personId, Position::DIRT_GREEN_DOT);
                $hasSanctuary = PersonPosition::havePosition($personId, Position::SANCTUARY);
                $hasGPGD = PersonPosition::havePosition($personId, Position::GERLACH_PATROL_GREEN_DOT);

                if (!$hasDirt && !$hasSanctuary) {
                    $errors[] = 'not a Green Dot';
                } else {
                    $positionIds = [];

                    if (!$hasDirt) {
                        $positionIds[] = Position::DIRT_GREEN_DOT;
                        $messages[] = 'added Dirt - Green Dot';
                    }

                    if (!$hasSanctuary) {
                        $positionIds[] = Position::SANCTUARY;
                        $messages[] = 'added Sanctuary';
                    }
                    if (!$hasGPGD) {
                        $positionIds[] = Position::GERLACH_PATROL_GREEN_DOT;
                        $messages[] = 'added Gerlach Patrol - Green Dot';
                    }
                    PersonPosition::addIdsToPerson($personId, $positionIds, 'position sanity checker repair');
                }

                $result = [
                    'id' => $personId,
                    'messages' => $messages
                ];

                if (!empty($errors)) {
                    $result['errors'] = $errors;
                }

                $results[] = $result;
            }
            return $results;

        case 'management-role':
            foreach ($peopleIds as $personId) {
                PersonRole::addIdsToPerson($personId, [ Role::MANAGE ], 'position sanity checker repair');
                $results[] = [ 'id' => $personId ];
            }
            return $results;

        case 'shiny-penny':
            $year = current_year();

            foreach ($peopleIds as $personId) {
                $hasPenny = PersonPosition::havePosition($personId, Position::DIRT_SHINY_PENNY);
                $isPenny = PersonMentor::retrieveYearPassed($personId) == $year;

                if ($hasPenny && !$isPenny) {
                    PersonPosition::removeIdsFromPerson($personId, [ Position::DIRT_SHINY_PENNY ], 'position sanity checker repair');
                    $message = 'not a Shiny Penny, position removed';
                } elseif (!$hasPenny && $isPenny) {
                    PersonPosition::addIdsToPerson($personId, [ Position::DIRT_SHINY_PENNY ], 'position sanity checker repair');
                    $message = 'is a Shiny Penny, position added';
                } else {
                    $message = 'Shiny Penny already has position. no repair needed.';
                }
                $results[] = [ 'id' => $personId, 'messages' => [ $message ]];
            }
            return $results;

        default:
            throw new \InvalidArgumentException("Unknown repair action [$repair]");
         }
    }
}

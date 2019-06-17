<?php

namespace App\Models;

use App\Models\ApiModel;

use Illuminate\Support\Facades\DB;

class Position extends ApiModel
{
    const ALPHA = 1;
    const DIRT = 2;
    const DIRT_TRAINING = 13;
    const DIRT_PRE_EVENT = 53;

    const DIRT_GREEN_DOT = 4;
    const GREEN_DOT_LEAD = 14;
    const GREEN_DOT_MENTOR = 40;
    const GREEN_DOT_MENTEE = 50;
    const GREEN_DOT_TRAINING = 101;
    const GREEN_DOT_TRAINER = 100;

    const MENTOR = 9;

    const HQ_WINDOW = 3;
    const HQ_TRAINER = 34;
    const HQ_FULL_TRAINING = 31;
    const HQ_LEAD = 32;
    const HQ_SHORT = 33;
    const HQ_RUNNER = 18;

    const DOUBLE_OH_7 = 21;

    const DPW_RANGER = 105;

    const OOD = 10;

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

    const TROUBLESHOOTER = 91;

    // Trainer positions for Dirt
    const TRAINING = 13;
    const TRAINER = 23;
    const TRAINER_ASSOCIATE = 88;
    const TRAINER_UBER = 95;

    const TOW_TRUCK_TRAINING = 102;
    const TOW_TRUCK_TRAINER = 121;

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
        Position::DIRT_TRAINING => [
             Position::TRAINER,
             Position::TRAINER_ASSOCIATE,
             Position::TRAINER_UBER
        ],
        Position::GREEN_DOT_TRAINING => [ Position::GREEN_DOT_TRAINER ],
        Position::HQ_FULL_TRAINING => [ Position::HQ_TRAINER ],
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

    protected $table = 'position';

    protected $fillable = [
        'all_rangers',
        'auto_signout',
        'count_hours',
        'max',
        'min',
        'new_user_eligible',
        'on_sl_report',
        'short_title',
        'title',
        'training_position_id',
        'type',
    ];

    protected $casts = [
        'all_rangers'       => 'bool',
        'auto_signout'      => 'bool',
        'new_user_eligible' => 'bool',
        'on_sl_report'      => 'bool',
    ];

    protected $rules = [
        'title' => 'required|string|max:40',
        'short_title' => 'sometimes|string|max:6|nullable',
        'min'   => 'integer',
        'max'   => 'integer',
        'training_position_id'  => 'nullable|exists:position,id',
    ];

    /*
     * Find all positions
     */

    public static function findAll()
    {
        return self::orderBy('title')->get();
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
            $sql = $sql->where('id', '!=', Position::DIRT_TRAINING);
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
}

<?php

namespace App\Models;

use App\Models\ApiModel;

class Position extends ApiModel
{

    const ALPHA = 1;
    const DIRT = 2;
    const DIRT_TRAINING = 13;

    const DIRT_GREEN_DOT = 4;
    const GREEN_DOT_LEAD = 14;
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

    const SANDMAN_TRAINING = 80;
    const SANDMAN_TRAINER = 108;

    const RSC_SHIFT_LEAD = 12;

    const TROUBLESHOOTER = 91;

    // Trainer positions for Dirt
    const TRAINING = 13;
    const TRAINER = 23;
    const TRAINER_IN_TRAINING = 88;
    const TRAINER_UBER = 95;

    const BURN_PERIMETER = 19;

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
    // TODO: change the position table schema to encapsulate this info.
    //

    const TRAINERS = [
        Position::DIRT_TRAINING => [
             Position::TRAINER,
             Position::TRAINER_IN_TRAINING,
             Position::TRAINER_UBER
        ],
        Position::GREEN_DOT_TRAINING => [ Position::GREEN_DOT_TRAINER ],
        Position::HQ_FULL_TRAINING => [ Position::HQ_TRAINER ],
        Position::SANDMAN_TRAINING => [ Position::SANDMAN_TRAINER ],
    ];

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
        'title' => 'required',
        'min'   => 'integer',
        'max'   => 'integer',
        'training_position_id'  => 'nullable|exists:position,id',
    ];

    public static function findAll()
    {
        return self::orderBy('title')->get();
    }

    public static function findAllTrainings($excludeDirt = false)
    {
        $sql = self::select('id','title')
            ->where('type', '=', 'Training')
            ->where('title', 'not like', '%trainer%')
            ->orderBy('title');

        if ($excludeDirt) {
            $sql = $sql->where('id', '!=', Position::DIRT_TRAINING);
        }

        return $sql->get()->toArray();
    }
}

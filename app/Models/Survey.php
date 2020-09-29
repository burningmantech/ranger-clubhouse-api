<?php

namespace App\Models;

use App\Models\ApiModel;

use App\Models\Position;
use App\Models\SurveyGroup;
use App\Models\Slot;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Survey extends ApiModel
{
    protected $table = 'survey';
    protected $auditModel = true;
    public $timestamps = true;

    const TRAINER = 'trainer';
    const TRAINING = 'training';
    const SLOT = 'slot';

    protected $fillable = [
        'year',
        'type',
        'title',
        'prologue',
        'epilogue',
        'position_id'
    ];

    protected $rules = [
        'year' => 'required|integer',
        'type' => 'required|string',
        'title' => 'required|string|max:190',
        'prologue' => 'sometimes|string',
        'epilogue' => 'sometimes|string',
        'position_id' => 'sometimes|integer|nullable',
    ];

    /**
     * Find all the surveys
     *
     * @return Survey[]|\Illuminate\Database\Eloquent\Collection
     */

    public static function findAll()
    {
        return self::orderBy('year')->get();
    }

    /**
     * Find all the surveys for a given year
     *
     * @param int $year
     * @return Survey[]|\Illuminate\Database\Eloquent\Collection
     */

    public static function findAllForYear(int $year)
    {
        return self::where('year', $year)->orderBy('type')->get();
    }

    /**
     * Find a survey of a type, position, and year.
     *
     * @param string $type
     * @param int $positionId
     * @param int $year
     * @return Survey
     * @throws ModelNotFoundException
     */

    public static function findForTypePositionYear(string $type, int $positionId, int $year)
    {
        return self::where('type', $type)
            ->where('position_id', $positionId)
            ->where('year', $year)
            ->with(['survey_groups', 'survey_groups.survey_questions', 'position:id,title'])
            ->firstOrFail();
    }

    /**
     * Find all the surveys based on the give criteria
     *
     * @param array $query
     * @return Survey[]|\Illuminate\Database\Eloquent\Collection
     */

    public static function findForQuery(array $query)
    {
        $year = $query['year'] ?? null;
        $positionId = $query['position_id'] ?? null;
        $includeSlots = $query['include_slots'] ?? false;

        $sql = self::with('position:id,title')
            ->orderBy('year');

        if ($year) {
            $sql->where('year', $year);
        }

        if ($positionId) {
            $sql->where('position_id', $positionId);
        }

        $rows = $sql->get();

        if (!$includeSlots) {
            return $rows;
        }

        foreach ($rows as $row) {
            if ($row->position_id) {
                $row->slots = $row->retrieveSlots();

                foreach ($row->slots as $slot) {
                    if ($slot->has_responses) {
                        $row->has_responses = true;
                        break;
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * Retrieve all the slots for the survey
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function retrieveSlots()
    {
        return Slot::select(
            'id',
            'begins',
            'ends',
            'description',
            'signed_up',
            DB::raw('EXISTS (SELECT 1 FROM survey_answer WHERE slot_id=slot.id LIMIT 1) AS has_responses'),
            DB::raw('IF(slot.ends < ? , TRUE, FALSE) as has_ended'),
            )->setBindings([now()])
            ->whereYear('begins', $this->year)
            ->where('position_id', $this->position_id)
            ->orderBy('begins')
            ->get();
    }

    /**
     * Retrieve all the surveys with feedback for the given person and optional year
     *
     * @param int $personId
     * @param $year
     * @return Collection
     */

    public static function retrieveAllForTrainer(int $personId)
    {
        return Survey::whereRaw('EXISTS (SELECT 1 FROM survey_answer WHERE survey_answer.survey_id=survey.id AND survey_answer.trainer_id=? LIMIT 1)', [$personId])
            ->with(['position:id,title'])
            ->get()
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'year' => $s->year,
                    'type' => $s->type,
                    'position_id' => $s->position_id,
                    'position_title' => $s->position->title
                ];
            })
            ->sortBy('year')
            ->values()
            ->groupBy('year');
    }

    /**
     * Find all surveys with answers for a person and year
     *
     * @param int $personId
     * @param int $year
     * @return Survey[]|\Illuminate\Database\Eloquent\Collection
     */
    public static function findAllForPersonYear(int $personId, int $year)
    {
        return Survey::whereRaw('EXISTS (SELECT 1 FROM survey_answer WHERE survey_answer.survey_id=survey.id AND survey_answer.trainer_id=? LIMIT 1)', [$personId])
            ->with(['position:id,title'])
            ->where('year', $year)
            ->get();
    }

    /**
     * Find any unanswered surveys for a person who has passed training in a given year.
     *
     * @param int $personId
     * @param int $year
     * @return array
     */

    public static function retrieveUnansweredForPersonYear(int $personId, int $year)
    {
        $slots = TraineeStatus::select('trainee_status.*')
            ->join('slot', 'slot.id', 'trainee_status.slot_id')
            ->whereYear('slot.begins', $year)
            ->where('person_id', $personId)
            ->where('passed', true)
            // is there a survey available?
            ->whereRaw('EXISTS (SELECT 1 FROM survey WHERE survey.year=? AND survey.position_id=slot.position_id LIMIT 1)', [$year])
            // Ensure person is still signed up
            ->whereRaw('EXISTS (SELECT 1 FROM person_slot WHERE person_slot.slot_id=trainee_status.slot_id AND person_slot.person_id=? LIMIT 1)', [$personId])
            // .. and have not responded to the survey
            ->whereRaw('NOT EXISTS (SELECT 1 FROM survey_answer JOIN survey ON survey.id=survey_answer.survey_id WHERE survey.position_id=slot.position_id AND survey.type != "trainer" AND survey_answer.person_id=? AND survey_answer.slot_id=trainee_status.slot_id LIMIT 1)', [$personId])
            ->with(['slot:id,description,begins,position_id', 'slot.position:id,title'])
            ->get()
            ->map(function ($s) {
                return [
                    'id' => $s->slot_id,
                    'begins' => (string)$s->slot->begins,
                    'description' => $s->slot->description,
                    'position_id' => $s->slot->position_id,
                    'position_title' => $s->slot->position->title,
                ];
            })->values()->toArray();

        $positionIds = [];
        foreach (Position::TRAINERS as $trainingId => $trainerIds) {
            $positionIds = array_merge($positionIds, $trainerIds);
        }

        $trainerSurveys = [];
        // Is the person a teacher of any kind?
        if (PersonPosition::havePosition($personId, $positionIds)) {
            // Find all the sessions the person taught (aka marked as attended)
            $taught = TrainerStatus::join('slot', 'slot.id', 'trainer_status.trainer_slot_id')
                ->whereIn('slot.position_id', $positionIds)
                ->whereYear('slot.begins', $year)
                ->where('trainer_status.person_id', $personId)
                ->where('trainer_status.status', TrainerStatus::ATTENDED)
                ->get();

            if (!$taught->isEmpty()) {
                // Find all the other co-trainers
                $slotIds = $taught->pluck('slot_id')->unique()->toArray();
                $trainerSlots = TrainerStatus::whereIn('slot_id', $slotIds)
                    ->where('trainer_status.person_id', '!=', $personId)
                    ->where('trainer_status.status', TrainerStatus::ATTENDED)
                    ->whereRaw('NOT EXISTS (SELECT 1 FROM survey_answer WHERE survey_answer.slot_id=trainer_status.slot_id AND survey_answer.trainer_id=trainer_status.person_id AND survey_answer.person_id=? LIMIT 1)', [$personId])
                    ->with(['slot:id,begins,description,position_id', 'slot.position:id,title', 'person:id,callsign', 'trainer_slot.position:id,title'])
                    ->get()
                    ->groupBy('trainee_status.slot_id');

                foreach ($trainerSlots as $slotId => $trainers) {
                    $slot = $trainers[0]->slot;

                    $trainerSurveys[] = [
                        'id' => $slot->id,
                        'description' => $slot->description,
                        'begins' => (string)$slot->begins,
                        'position_id' => $slot->position_id,
                        'position_title' => $slot->position->title,
                        'trainers' => $trainers->map(function ($t) {
                            return [
                                'id' => $t->person_id,
                                'callsign' => $t->person->callsign,
                                'position_title' => $t->trainer_slot->position->title,
                            ];
                        })->values()->toArray()
                    ];
                }

            }
        }

        return [
            'sessions' => $slots,
            'trainers' => $trainerSurveys
        ];
    }

    public function survey_groups()
    {
        return $this->hasMany(SurveyGroup::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function setPositionIdAttribute($value)
    {
        $this->attributes['position_id'] = empty($value) ? null : $value;
    }
}

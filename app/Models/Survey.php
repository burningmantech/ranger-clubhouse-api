<?php

namespace App\Models;

use App\Attributes\NullIfEmptyAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Survey extends ApiModel
{
    protected $table = 'survey';
    protected bool $auditModel = true;
    public $timestamps = true;

    const string TRAINER = 'trainer';// Survey is for trainers on trainers.
    const string TRAINING = 'training'; // Survey is for a training session
    const string ALPHA = 'alpha';  // Survey is for Alphas
    const string MENTOR_FOR_MENTEES = 'mentor-for-mentees'; // rate mentees by the mentors
    const string MENTEES_FOR_MENTOR = 'mentees-for-mentor'; // rate the mentors by the mentees

    const array TYPE_FOR_REPORTS_LABELS = [
        self::TRAINER => "Trainer-on-Trainer Feedback",
        self::TRAINING => "Trainee-on-Trainer Feedback",
        self::ALPHA => "Alpha Feedback",
        self::MENTEES_FOR_MENTOR => "Mentee-on-Mentor Feedback",
        self::MENTOR_FOR_MENTEES => "Mentor-on-Mentee Feedback"
    ];

    protected $fillable = [
        'active',
        'year',
        'type',
        'title',
        'prologue',
        'epilogue',
        'position_id',
        // For MENTOR_FOR_MENTEES or MENTOR_FOR_MENTEES is the position to rate, position_id becomes the shifts
        // to find for when the person worked.
        'mentoring_position_id',
    ];

    protected $appends = [
        'position_title',
        'mentoring_position_title',
    ];

    protected $rules = [
        'year' => 'required|integer',
        'type' => 'required|string',
        'title' => 'required|string|max:190',
        'prologue' => 'sometimes|string',
        'epilogue' => 'sometimes|string',
        'position_id' => 'sometimes|integer|nullable',
        'mentoring_position_id' => 'sometimes|integer|nullable',
    ];

    public function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public static function boot(): void
    {
        parent::boot();

        self::deleted(function ($model) {
            foreach (['survey_group', 'survey_question', 'survey_answer'] as $table) {
                DB::table($table)->where('survey_id', $model->id)->delete();
            }
        });
    }

    public function survey_group(): HasMany
    {
        return $this->hasMany(SurveyGroup::class);
    }

    public function survey_groups(): HasMany
    {
        return $this->hasMany(SurveyGroup::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function mentoring_position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Find all the surveys
     *
     * @return Collection
     */

    public static function findAll(): Collection
    {
        return self::orderBy('year')->get();
    }

    /**
     * Find a survey of a type, position, and year.
     *
     * @param string $type
     * @param int $positionId
     * @param int $year
     * @return Model
     * @throws ModelNotFoundException
     */

    public static function findForTypePositionYear(string $type, int $positionId, int $year): Model
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
     * @return \Illuminate\Database\Eloquent\Collection
     */

    public static function findForQuery(array $query): \Illuminate\Database\Eloquent\Collection
    {
        $year = $query['year'] ?? null;
        $positionId = $query['position_id'] ?? null;
        $includeSlots = $query['include_slots'] ?? false;
        $type = $query['type'] ?? null;

        $sql = self::with('position:id,title')
            ->with('survey_group')
            ->orderBy('year');

        if ($year) {
            $sql->where('year', $year);
        }

        if ($positionId) {
            $sql->where('position_id', $positionId);
        }

        if ($type) {
            $sql->where('type', $type);
        }

        $rows = $sql->get();

        foreach ($rows as $row) {
            if (!$includeSlots && $row->type != self::ALPHA) {
                continue;
            }

            if ($row->position_id) {
                $row->slots = $row->retrieveSlots();

                foreach ($row->slots as $slot) {
                    if ($slot->has_responses) {
                        $row->has_responses = true;
                        break;
                    }
                }
            }

            $reports = [
                [
                    'id' => 'main',
                    'title' => match ($row->type) {
                        self::ALPHA => 'Alpha Feedback',
                        self::TRAINER => 'Trainer-On-Trainer Feedback',
                        default => ($row->position_id == Position::TRAINING) ? 'Venue Feedback' : 'General Questions',
                    },
                ]
            ];

            foreach ($row->survey_group as $group) {
                if ($group->type != SurveyGroup::TYPE_NORMAL) {
                    $reports[] = [
                        'id' => $group->getReportId(),
                        'title' => $group->getReportTitleDefault($row->type),
                    ];
                }
            }

            $row->reports = $reports;
        }

        return $rows;
    }

    public function save($options = []): bool
    {
        if (($this->type == self::MENTOR_FOR_MENTEES || $this->type == self::MENTEES_FOR_MENTOR) && !$this->mentoring_position_id) {
            $this->addError('mentoring_position_id', 'May not be blank for mentor-for-mentees and mentees-for-mentor types');
            return false;
        }

        return parent::save($options);
    }

    /**
     * Retrieve all the slots for the survey
     *
     * @return Collection
     */

    public function retrieveSlots(): Collection
    {
        return Slot::select(
            'id',
            'begins',
            'ends',
            'timezone',
            'description',
            'signed_up',
            DB::raw('EXISTS (SELECT 1 FROM survey_answer WHERE slot_id=slot.id LIMIT 1) AS has_responses'),
        )->where('begins_year', $this->year)
            ->where('position_id', $this->position_id)
            ->orderBy('begins')
            ->get();
    }

    /**
     * Retrieve all the surveys with feedback for the given person and optional year
     *
     * @param int $personId
     * @return Collection
     */

    public static function retrieveAllForTrainer(int $personId): Collection
    {
        return Survey::whereRaw('EXISTS (SELECT 1 FROM survey_answer WHERE survey_answer.survey_id=survey.id AND survey_answer.trainer_id=? LIMIT 1)', [$personId])
            ->with(['position:id,title'])
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'year' => $s->year,
                'type' => $s->type,
                'position_id' => $s->position_id,
                'position_title' => $s->position?->title,
                'title' => $s->title,
            ])
            ->sortBy('year')
            ->values()
            ->groupBy('year');
    }

    /**
     * Find all surveys with answers for a trainer and year
     *
     * @param int $personId
     * @param int $year
     * @param string|null $type
     * @return \Illuminate\Database\Eloquent\Collection
     */

    public static function findAllForTrainerYear(int $personId, int $year, ?string $type): \Illuminate\Database\Eloquent\Collection
    {
        $sql = Survey::whereRaw('EXISTS (SELECT 1 FROM survey_answer WHERE survey_answer.survey_id=survey.id AND survey_answer.trainer_id=? LIMIT 1)', [$personId])
            ->with(['position:id,title'])
            ->where('year', $year);

        if (!empty($type)) {
            $sql->where('type', $type);
        } else {
            $sql->where('type', '!=', Survey::ALPHA);
        }

        return $sql->get();
    }

    /**
     * Does a trainer survey exists for a training position?
     *
     * @param int $positionId
     * @return bool
     */

    public static function hasTrainerSurveyForPosition(int $positionId): bool
    {
        return self::where('type', self::TRAINER)
            ->where('year', current_year())
            ->where('position_id', $positionId)
            ->exists();
    }

    /**
     * Find any unanswered surveys for a person who has passed training in a given year.
     *
     * @param int $personId
     * @param int $year
     * @param string|null $status
     * @return array
     */

    public static function retrieveUnansweredForPersonYear(int $personId, int $year, ?string $status): array
    {
        $slots = TraineeStatus::select('trainee_status.*')
            ->join('slot', 'slot.id', 'trainee_status.slot_id')
            ->where('slot.begins_year', $year)
            ->where('person_id', $personId)
            ->where('passed', true)
            // is there a survey available?
            ->whereRaw('EXISTS (SELECT 1 FROM survey WHERE survey.active = TRUE AND survey.year=? AND survey.position_id=slot.position_id AND survey.type=? LIMIT 1)', [$year, self::TRAINING])
            // Ensure person is still signed up
            ->whereRaw('EXISTS (SELECT 1 FROM person_slot WHERE person_slot.slot_id=trainee_status.slot_id AND person_slot.person_id=? LIMIT 1)', [$personId])
            // .. and have not responded to the survey
            ->whereRaw('NOT EXISTS (SELECT 1 FROM survey_answer JOIN survey ON survey.id=survey_answer.survey_id WHERE survey.position_id=slot.position_id AND survey.type != "trainer" AND survey_answer.person_id=? AND survey_answer.slot_id=trainee_status.slot_id LIMIT 1)', [$personId])
            ->with(['slot:id,description,begins,position_id', 'slot.position:id,title'])
            ->get()
            ->map(fn($s) => [
                'id' => $s->slot_id,
                'type' => self::TRAINING,
                'begins' => (string)$s->slot->begins,
                'description' => $s->slot->description,
                'position_id' => $s->slot->position_id,
                'position_title' => $s->slot->position->title,
            ])->values()->toArray();

        $positionIds = [];

        foreach (Position::TRAINERS as $trainingId => $trainerIds) {
            $positionIds = array_merge($positionIds, $trainerIds);
        }

        $trainerSurveys = [];
        // Is the person a teacher of any kind?
        if (PersonPosition::havePosition($personId, $positionIds)) {
            // Find all the sessions the person taught (aka marked as attended)
            $taught = TrainerStatus::join('slot', 'slot.id', 'trainer_status.trainer_slot_id')
                ->whereIntegerInRaw('slot.position_id', $positionIds)
                ->where('slot.begins_year', $year)
                ->where('trainer_status.person_id', $personId)
                ->where('trainer_status.status', TrainerStatus::ATTENDED)
                ->get();

            if (!$taught->isEmpty()) {
                // Find all the other co-trainers
                $slotIds = $taught->pluck('slot_id')->unique()->toArray();
                $trainerSlots = TrainerStatus::select('trainer_status.*')
                    ->join('slot', 'slot.id', 'trainer_status.slot_id')
                    ->whereIntegerInRaw('slot_id', $slotIds)
                    ->where('trainer_status.person_id', '!=', $personId)
                    ->where('trainer_status.status', TrainerStatus::ATTENDED)
                    // is there a survey available?
                    ->whereRaw('EXISTS (SELECT 1 FROM survey WHERE survey.active = TRUE AND survey.year=? AND survey.position_id=slot.position_id AND survey.type=? LIMIT 1)', [$year, self::TRAINER])
                    ->whereRaw('NOT EXISTS (SELECT 1 FROM survey_answer WHERE survey_answer.slot_id=trainer_status.slot_id AND survey_answer.trainer_id=trainer_status.person_id AND survey_answer.person_id=? LIMIT 1)', [$personId])
                    ->with(['slot:id,begins,description,position_id', 'slot.position:id,title', 'person:id,callsign', 'trainer_slot.position:id,title'])
                    ->get()
                    ->groupBy('slot_id');

                foreach ($trainerSlots as $slotId => $trainers) {
                    $slot = $trainers[0]->slot;

                    $trainerSurveys[] = [
                        'id' => $slot->id,
                        'type' => self::TRAINER,
                        'description' => $slot->description,
                        'begins' => (string)$slot->begins,
                        'position_id' => $slot->position_id,
                        'position_title' => $slot->position->title,
                        'trainers' => $trainers->map(fn($t) => [
                            'id' => $t->person_id,
                            'callsign' => $t->person->callsign,
                            'position_title' => $t->trainer_slot->position->title,
                        ])->values()->toArray()
                    ];
                }

            }
        }

        $alphaSurvey = false;
        if ($status == Person::ACTIVE
            && Timesheet::hasAlphaEntry($personId, $year)
            && PersonMentor::didPersonPass($personId, $year)
            && SurveyAnswer::needAlphaSurveyResponse($personId, $year)) {
            $alphaSurvey = true;
        }

        // Mentor surveys
        $mentoringSurveys = Survey::where('year', $year)
            ->join('person_position', 'person_position.position_id', 'survey.position_id')
            ->where('survey.active', true)
            ->where('person_position.person_id', $personId)
            ->whereIn('type', [self::MENTEES_FOR_MENTOR, self::MENTOR_FOR_MENTEES])
            ->with('position:id,title')
            ->get();

        foreach ($mentoringSurveys as $m) {
            $shifts = Timesheet::where('position_id', $m->position_id)
                ->where('person_id', $personId)
                ->whereYear('on_duty', $year)
                ->whereNotNull('slot_id')
                ->whereNotExists(function ($q) use ($m, $personId) {
                    $q->select(DB::raw(1))
                        ->from('survey_answer')
                        ->where('survey_answer.survey_id', $m->id)
                        ->whereColumn('survey_answer.slot_id', 'timesheet.slot_id')
                        ->where('survey_answer.person_id', $personId)
                        ->limit(1);
                })
                ->orderBy('on_duty')
                ->with(['slot', 'slot.position:id,title'])
                ->get();

            foreach ($shifts as $shift) {
                $slot = $shift->slot;
                if (!$slot) {
                    continue;
                }

                $targets = Timesheet::where('position_id', $m->mentoring_position_id)
                    ->whereRaw('on_duty BETWEEN DATE_SUB(?, INTERVAL 1 HOUR) AND DATE_ADD(?, INTERVAL 1 HOUR)', [$slot->begins, $slot->begins])
                    ->get();

                if ($targets->isEmpty()) {
                    continue;
                }

                $slots[] = [
                    'id' => $slot->id,
                    'type' => $m->type,
                    'begins' => (string)$slot->begins,
                    'position_id' => $m->position_id,
                    'position_title' => $m->position->title,
                    'mentoring' => $targets->map(fn($t) => [
                        'id' => $t->person_id,
                        'callsign' => $t->person->callsign,
                    ])->toArray()
                ];
            }
        }

        return [
            'sessions' => $slots,
            'trainers' => $trainerSurveys,
            'alpha_survey' => $alphaSurvey
        ];
    }

    public function positionId(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function mentoringPositionId(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function getPositionTitleAttribute(): ?string
    {
        return $this->position?->title;
    }

    public function getMentoringPositionTitleAttribute(): ?string
    {
        return $this->mentoring_position_id ? $this->mentoring_position?->title : '';
    }
}

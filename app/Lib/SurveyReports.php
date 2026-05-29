<?php

namespace App\Lib;

use App\Exceptions\UnacceptableConditionException;
use App\Models\Person;
use App\Models\PersonMentor;
use App\Models\Position;
use App\Models\Slot;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyGroup;
use App\Models\SurveyQuestion;
use App\Models\Timesheet;
use App\Models\TraineeStatus;
use App\Models\TrainerStatus;
use InvalidArgumentException;

class SurveyReports
{
    private const MENTORING_WINDOW_HOURS = 1;

    /**
     * Retrieve the slot, the survey for the type/position/year, and the attending trainers
     * for whom the responding person has not yet submitted answers.
     *
     * @throws UnacceptableConditionException
     */
    public static function retrieveSlotSurveyTrainers(string $type, int $slotId, Person $person): array
    {
        $personId = $person->id;
        $slot = Slot::findOrFail($slotId);
        $survey = Survey::findForTypePositionYear($type, $slot->position_id, $slot->begins->year);

        $trainers = TrainerStatus::where('slot_id', $slotId)
            ->where('trainer_status.status', TrainerStatus::ATTENDED)
            ->whereNotExists(function ($query) use ($personId) {
                $query->from('survey_answer')
                    ->whereColumn('survey_answer.slot_id', 'trainer_status.slot_id')
                    ->whereColumn('survey_answer.trainer_id', 'trainer_status.person_id')
                    ->where('survey_answer.person_id', $personId);
            })
            ->with([
                'person:id,callsign,person_photo_id',
                'person.person_photo',
                'trainer_slot',
                'trainer_slot.position:id,title',
            ])
            ->get()
            ->map(function ($trainerStatus) {
                $trainer = $trainerStatus->person;
                $trainerSlot = $trainerStatus->trainer_slot;
                return (object) [
                    'id' => $trainerStatus->person_id,
                    'callsign' => $trainer->callsign,
                    'position_id' => $trainerSlot->position_id,
                    'position_title' => $trainerSlot->position->title ?? "Position #{$trainerSlot->position_id}",
                    'photo_url' => $trainer->approvedProfileUrl(),
                ];
            })
            ->sortBy('callsign')
            ->values();

        switch ($type) {
            case Survey::TRAINING:
                if (!TraineeStatus::didPersonPassSession($personId, $slotId)) {
                    throw new UnacceptableConditionException('You are not marked as having attended the training session.');
                }
                break;

            case Survey::TRAINER:
                if (!$trainers->firstWhere('id', $personId)) {
                    throw new UnacceptableConditionException('You are not marked as having taught the training session.');
                }
                $trainers = $trainers->filter(fn($trainer) => ($trainer->id != $personId))->values();
                break;

            default:
                throw new UnacceptableConditionException("Unknown survey type [$type]");
        }

        return [$slot, $survey, $trainers];
    }

    /**
     * Retrieve the alpha survey and the alpha's mentors for the given year.
     */
    public static function retrieveAlphaSurvey(int $year, Person $person): array
    {
        $personId = $person->id;
        if (!Timesheet::hasAlphaEntry($personId, $year)) {
            throw new InvalidArgumentException("Person was not an alpha in the given year");
        }

        $survey = Survey::findForTypePositionYear(Survey::ALPHA, Position::ALPHA, $year);
        $mentors = PersonMentor::where('mentor_year', $year)
            ->where('person_id', $personId)
            ->with(['mentor:id,callsign,person_photo_id', 'mentor.person_photo'])
            ->get()
            ->map(function ($mentorship) {
                $mentor = $mentorship->mentor;
                return (object) [
                    'id' => $mentor->id,
                    'callsign' => $mentor->callsign,
                    'photo_url' => $mentor->approvedProfileUrl(),
                ];
            })
            ->sortBy('callsign')
            ->values();

        return [$survey, $mentors];
    }

    /**
     * Retrieve the slot, survey, and the people who worked the mentored position
     * within a window around the mentoring session start time.
     */
    public static function retrieveMentoringSurvey(string $type, int $slotId, Person $person): array
    {
        $slot = Slot::findOrFail($slotId);
        $survey = Survey::findForTypePositionYear($type, $slot->position_id, $slot->begins->year);

        $windowStart = $slot->begins->copy()->subHours(self::MENTORING_WINDOW_HOURS);
        $windowEnd = $slot->begins->copy()->addHours(self::MENTORING_WINDOW_HOURS);

        $targets = Timesheet::where('position_id', $survey->mentoring_position_id)
            ->whereBetween('on_duty', [$windowStart, $windowEnd])
            ->with([
                'person:id,callsign,person_photo_id',
                'person.person_photo',
                'position:id,title',
            ])
            ->get()
            ->map(function ($timesheet) {
                $target = $timesheet->person;
                return (object) [
                    'id' => $timesheet->person_id,
                    'callsign' => $target->callsign,
                    'position_id' => $timesheet->position_id,
                    'position_title' => $timesheet->position->title ?? "Position #{$timesheet->position_id}",
                    'photo_url' => $target->approvedProfileUrl(),
                ];
            })
            ->sortBy('callsign')
            ->values();

        return [$slot, $survey, $targets->toArray()];
    }

    /**
     * Build the complete set of reports for a survey, optionally filtered to a single trainer.
     */
    public static function buildSurveyReports(Survey $survey, ?int $trainerId = null, bool $includePerson = false): array
    {
        $slots = $survey->retrieveSlots();
        $surveyGroups = SurveyGroup::findAllForSurvey($survey->id);
        $questions = SurveyQuestion::findAllForSurvey($survey->id);
        $questionsByGroupId = $questions->groupBy('survey_group_id');

        $answerQuery = SurveyAnswer::where('survey_id', $survey->id)
            ->where('response', '!=', '')
            ->with(['trainer:id,callsign', 'person:id,callsign']);

        if ($trainerId) {
            $answerQuery->where('trainer_id', $trainerId);
        }

        $surveyAnswers = $answerQuery->get();
        $answersByQuestionId = $surveyAnswers->groupBy('survey_question_id');

        $trainerIds = $surveyAnswers->where('trainer_id', '>', 0)->pluck('trainer_id')->unique()->values();
        $trainers = $trainerIds->isEmpty()
            ? collect()
            : Person::select('id', 'callsign', 'person_photo_id')
                ->whereIntegerInRaw('id', $trainerIds)
                ->with('person_photo')
                ->get();

        $mainReport = (object) [
            'id' => 'main',
            'type' => SurveyGroup::TYPE_NORMAL,
        ];

        $reports = [];
        foreach ($surveyGroups as $group) {
            $groupQuestions = $questionsByGroupId->get($group->id);
            if (!$groupQuestions) {
                continue;
            }

            if ($trainerId && !$surveyAnswers->first(
                fn($answer) => $answer->survey_group_id == $group->id && $answer->trainer_id == $trainerId
            )) {
                continue;
            }

            $report = $group->type == SurveyGroup::TYPE_NORMAL
                ? $mainReport
                : (object) ['id' => $group->getReportId(), 'type' => $group->type];

            switch ($group->type) {
                case SurveyGroup::TYPE_NORMAL:
                case SurveyGroup::TYPE_SEPARATE:
                    self::buildGroupReport($survey, $report, $slots, $groupQuestions, $answersByQuestionId, $trainerId, $includePerson);
                    break;
                case SurveyGroup::TYPE_TRAINER:
                    self::buildTrainerGroupReport($survey, $report, $trainers, $slots, $groupQuestions, $surveyAnswers, $includePerson);
                    break;
                case SurveyGroup::TYPE_SUMMARY:
                    self::buildSummaryGroupReport($report, $groupQuestions, $answersByQuestionId, $includePerson);
                    break;
            }

            if ($report !== $mainReport) {
                $reports[] = $report;
            }
        }

        if (isset($mainReport->questions)) {
            array_unshift($reports, $mainReport);
        }

        return $reports;
    }

    /**
     * Build a normal or separate-slot group report by iterating each session and question.
     */
    public static function buildGroupReport(
        Survey $survey,
        object $report,
        $slots,
        $questions,
        $answersByQuestionId,
        ?int $trainerId,
        bool $includePerson
    ): void {
        if (!isset($report->questions)) {
            $report->questions = [];
            $report->summarized_ratings = [];
        }

        $isAlpha = ($survey->type == Survey::ALPHA);
        foreach ($questions as $question) {
            $answersForQuestion = $answersByQuestionId->get($question->id);
            if (!$answersForQuestion) {
                continue;
            }
            $isRating = ($question->type == SurveyQuestion::TYPE_RATING) || $question->summarize_rating;
            $buckets = self::bucketAnswers(
                $question,
                $answersForQuestion,
                $slots,
                $isAlpha,
                $isRating,
                $includePerson,
                (bool) $trainerId
            );

            $responseKey = $isAlpha ? 'responses' : 'slots';
            $entries = $isAlpha ? $buckets['alphaResponses'] : $buckets['slotResponses'];

            if ($question->summarize_rating) {
                if (!$isAlpha) {
                    self::sortStatistics($entries);
                }
                $report->summarized_ratings[] = [
                    'description' => $question->description,
                    $responseKey => $entries,
                ];
            } else {
                $data = [
                    'id' => $question->id,
                    'type' => $question->type,
                    'description' => $question->description,
                    $responseKey => $entries,
                ];
                if ($isRating) {
                    $data += self::computeStatistics($buckets['overallRatings']);
                }
                $report->questions[] = $data;
            }
        }
    }

    /**
     * Build a trainer-feedback group report: iterate trainers, then each trainer's questions/sessions.
     */
    public static function buildTrainerGroupReport(
        Survey $survey,
        object $report,
        $trainers,
        $slots,
        $questions,
        $surveyAnswers,
        bool $includePerson
    ): void {
        $answersByTrainerId = $surveyAnswers
            ->filter(fn($answer) => $answer->trainer_id > 0)
            ->groupBy('trainer_id');

        $trainerReports = [];
        $questionSummaryRatings = [];
        $isAlpha = ($survey->type == Survey::ALPHA);

        foreach ($trainers as $trainer) {
            $answersForTrainer = $answersByTrainerId->get($trainer->id);
            if (!$answersForTrainer) {
                continue;
            }

            $trainerReport = [
                'trainer_id' => $trainer->id,
                'callsign' => $trainer->callsign,
                'photo_url' => $trainer->approvedProfileUrl(),
            ];

            $answersForTrainerByQuestion = $answersForTrainer->groupBy('survey_question_id');

            foreach ($questions as $question) {
                $answersForQuestion = $answersForTrainerByQuestion->get($question->id);
                if (!$answersForQuestion) {
                    continue;
                }
                $isRating = ($question->type == SurveyQuestion::TYPE_RATING) || $question->summarize_rating;
                $buckets = self::bucketAnswers(
                    $question,
                    $answersForQuestion,
                    $slots,
                    $isAlpha,
                    $isRating,
                    $includePerson,
                    true
                );

                if ($question->summarize_rating) {
                    $summary = [
                        'trainer_id' => $trainer->id,
                        'callsign' => $trainer->callsign,
                        'ratings' => $buckets['overallRatings'],
                    ];
                    $summary += self::computeStatistics($buckets['overallRatings']);
                    $questionSummaryRatings[$question->id][] = $summary;
                } else {
                    $data = [
                        'id' => $question->id,
                        'type' => $question->type,
                        'description' => $question->description,
                    ];
                    $data[$isAlpha ? 'responses' : 'slots'] = $isAlpha
                        ? $buckets['alphaResponses']
                        : $buckets['slotResponses'];
                    if ($isRating) {
                        $data += self::computeStatistics($buckets['overallRatings']);
                    }
                    $trainerReport['questions'][] = $data;
                }
            }
            $trainerReports[] = $trainerReport;
        }

        if (!empty($questionSummaryRatings)) {
            $summarizedRatings = [];
            foreach ($questionSummaryRatings as $questionId => $summaries) {
                $question = $questions->firstWhere('id', $questionId);
                self::sortStatistics($summaries);
                $summarizedRatings[] = [
                    'description' => $question->description,
                    'trainers' => $summaries,
                ];
            }
            $report->summarized_ratings = $summarizedRatings;
        }

        usort($trainerReports, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));
        $report->trainers = $trainerReports;
    }

    /**
     * Build a summary group report: collapse all responses into a single non-slot grouping.
     */
    public static function buildSummaryGroupReport(
        object $report,
        $questions,
        $answersByQuestionId,
        bool $includePerson
    ): void {
        $questionResponses = [];
        foreach ($questions as $question) {
            $answers = $answersByQuestionId->get($question->id);
            if (!$answers) {
                continue;
            }

            $isRating = ($question->type == SurveyQuestion::TYPE_RATING);
            $ratings = [];
            $responses = [];
            foreach ($answers as $answer) {
                if ($isRating) {
                    $ratings[] = (int) $answer->response;
                } else {
                    $responses[] = self::buildAnswer($answer, $includePerson);
                }
            }

            $entry = ['description' => $question->description, 'type' => $question->type];
            if ($isRating) {
                $entry += self::computeStatistics($ratings);
            } else {
                $entry['responses'] = $responses;
            }
            $questionResponses[] = $entry;
        }

        $report->questions = $questionResponses;
    }

    /**
     * Bucket a question's answers either as a flat list (alpha surveys) or grouped by slot.
     *
     * @return array{
     *     overallRatings: int[],
     *     alphaResponses: array<int,array>|null,
     *     slotResponses: array<int,array>|null
     * }
     */
    private static function bucketAnswers(
        SurveyQuestion $question,
        $answersForQuestion,
        $slots,
        bool $isAlpha,
        bool $isRating,
        bool $includePerson,
        bool $skipEmptySlots
    ): array {
        $overallRatings = [];

        if ($isAlpha) {
            $alphaResponses = [];
            foreach ($answersForQuestion as $answer) {
                if ($isRating) {
                    $overallRatings[] = (int) $answer->response;
                } elseif (!$question->summarize_rating) {
                    $alphaResponses[] = self::buildAnswer($answer, $includePerson);
                }
            }
            return [
                'overallRatings' => $overallRatings,
                'alphaResponses' => $alphaResponses,
                'slotResponses' => null,
            ];
        }

        $answersBySlot = $answersForQuestion->groupBy('slot_id');
        $slotResponses = [];
        foreach ($slots as $slot) {
            $answersForSlot = $answersBySlot->get($slot->id);
            if ($skipEmptySlots && !$answersForSlot) {
                continue;
            }

            $responses = [];
            $ratings = [];
            foreach ($answersForSlot ?? [] as $answer) {
                if ($isRating) {
                    $overallRatings[] = $ratings[] = (int) $answer->response;
                } elseif (!$question->summarize_rating) {
                    $responses[] = self::buildAnswer($answer, $includePerson);
                }
            }

            $slotEntry = [
                'slot_id' => $slot->id,
                'slot_begins' => (string) $slot->begins,
                'slot_description' => $slot->description,
            ];
            if ($isRating) {
                $slotEntry += self::computeStatistics($ratings);
            } else {
                $slotEntry['responses'] = $responses;
            }
            $slotResponses[] = $slotEntry;
        }

        return [
            'overallRatings' => $overallRatings,
            'alphaResponses' => null,
            'slotResponses' => $slotResponses,
        ];
    }

    /**
     * Compute mean, variance, distribution, and count for a set of ratings.
     *
     * @param int[] $ratings
     * @return array{mean: float, variance: float, distribution: array<int,int>, rating_count: int}
     */
    public static function computeStatistics(array $ratings): array
    {
        $count = count($ratings);
        if ($count === 0) {
            return ['mean' => 0.0, 'variance' => 0.0, 'distribution' => [], 'rating_count' => 0];
        }

        $mean = array_sum($ratings) / $count;
        $squaredDiffs = 0.0;
        $distribution = [];
        foreach ($ratings as $rating) {
            $squaredDiffs += ($rating - $mean) ** 2;
            $distribution[$rating] = ($distribution[$rating] ?? 0) + 1;
        }
        ksort($distribution);

        return [
            'mean' => round($mean, 1),
            'variance' => round($squaredDiffs / $count, 1),
            'distribution' => $distribution,
            'rating_count' => $count,
        ];
    }

    /**
     * Sort statistics descending by mean, breaking ties with rating_count.
     */
    public static function sortStatistics(array &$stats): void
    {
        usort(
            $stats,
            fn($a, $b) => [$b['mean'], $b['rating_count']] <=> [$a['mean'], $a['rating_count']]
        );
    }

    /**
     * Build the answer payload, optionally including the respondent's identity.
     *
     * For surveys imported from pre-2020 forms, the callsign may not map to a
     * Clubhouse account; in that case person_id is 0 and the raw callsign is used.
     */
    public static function buildAnswer(SurveyAnswer $answer, bool $includePerson): array
    {
        if (!$includePerson) {
            return ['answer' => $answer->response];
        }

        $person = ($answer->person_id && $answer->person)
            ? ['id' => $answer->person->id, 'callsign' => $answer->person->callsign]
            : ['id' => 0, 'callsign' => $answer->callsign];

        return ['answer' => $answer->response, 'person' => $person];
    }

    /**
     * Report on a trainer for a given year.
     */
    public static function trainerReportForYear(int $trainerId, int $year, ?string $type): array
    {
        $surveys = Survey::findAllForTrainerYear($trainerId, $year, $type);

        $surveyReports = [];
        foreach ($surveys as $survey) {
            $surveyReports[] = [
                'id' => $survey->id,
                'year' => $survey->year,
                'type' => $survey->type,
                'title' => $survey->title,
                'position_id' => $survey->position_id,
                'position_title' => $survey->position->title,
                'reports' => self::buildSurveyReports($survey, $trainerId, false),
            ];
        }

        return $surveyReports;
    }

    /**
     * Report on all trainers who have feedback for a given survey.
     */
    public static function allTrainersReport(Survey $survey): array
    {
        $foundTrainers = SurveyAnswer::join('person', 'survey_answer.trainer_id', 'person.id')
            ->select('trainer_id', 'person.callsign')
            ->where('survey_id', $survey->id)
            ->groupBy('trainer_id', 'person.callsign')
            ->with(['trainer.person_photo'])
            ->get();

        $trainers = [];
        foreach ($foundTrainers as $trainer) {
            $reports = self::buildSurveyReports($survey, $trainer->trainer_id, true);
            $trainerReport = null;
            foreach ($reports as $candidate) {
                if ($candidate->type == SurveyGroup::TYPE_TRAINER) {
                    $trainerReport = $candidate;
                    break;
                }
            }

            $trainers[] = [
                'id' => $trainer->trainer_id,
                'callsign' => $trainer->callsign,
                'photo_url' => $trainer->trainer->approvedProfileUrl(),
                'report' => $trainerReport ?? [],
            ];
        }

        usort($trainers, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));

        return $trainers;
    }
}

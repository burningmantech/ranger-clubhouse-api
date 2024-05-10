<?php

namespace App\Lib;

use App\Exceptions\UnacceptableConditionException;
use App\Models\Person;
use App\Models\PersonMentor;
use App\Models\Position;
use App\Models\Role;
use App\Models\Slot;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyGroup;
use App\Models\SurveyQuestion;
use App\Models\Timesheet;
use App\Models\TraineeStatus;
use App\Models\TrainerStatus;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class SurveyReports
{
    /**
     * Retrieve the slot record, the survey for the type, position & year and the attending trainers
     *
     * @param string $type
     * @param int $slotId
     * @param int $personId
     * @return array
     */

    public static function retrieveSlotSurveyTrainers(string $type, int $slotId, int $personId): array
    {
        $slot = Slot::findOrFail($slotId);

        $survey = Survey::findForTypePositionYear($type, $slot->position_id, $slot->begins->year);
        $trainers = TrainerStatus::where('slot_id', $slotId)
            ->where('trainer_status.status', TrainerStatus::ATTENDED)
            ->whereRaw('NOT EXISTS (SELECT 1 FROM survey_answer WHERE survey_answer.slot_id=trainer_status.slot_id AND survey_answer.trainer_id=trainer_status.person_id AND survey_answer.person_id=? LIMIT 1)', [$personId])
            ->with(['person:id,callsign,person_photo_id', 'person.person_photo', 'trainer_slot', 'trainer_slot.position:id,title'])
            ->get()
            ->map(function ($t) {
                $p = $t->person;
                return (object)[
                    'id' => $t->person_id,
                    'callsign' => $p->callsign,
                    'position_id' => $t->trainer_slot->position_id,
                    'position_title' => $t->trainer_slot->position->title ?? "Position #{$t->trainer_slot->position_id}",
                    'photo_url' => $p->person_photo->image_url ?? null,
                ];
            })->sortBy('callsign')
            ->values();

        switch ($type) {
            case Survey::TRAINING:
                if (!TraineeStatus::didPersonPassSession($personId, $slotId)) {
                    throw new UnacceptableConditionException('You are not marked as having attended the training session.');
                }
                break;

            case Survey::TRAINER:
                $me = $trainers->firstWhere('id', $personId);
                if (!$me) {
                    throw new UnacceptableConditionException('You are not marked as having taught the training session.');
                }
                // Exclude the person's answers.
                $trainers = $trainers->filter(fn($t) => ($t->id != $personId));
                break;

            default:
                throw new UnacceptableConditionException("Unknown survey type [$type]");
        }

        return [$slot, $survey, $trainers->values()];
    }

    /**
     * Retrieve the slot record, the survey for the type, position & year and the attending trainers
     *
     * @param int $year
     * @param int $personId
     * @return array
     */

    public static function retrieveAlphaSurvey(int $year, int $personId): array
    {
        if (Timesheet::hasAlphaEntry($personId, $year)) {
            throw new InvalidArgumentException("Person was not an alpha in the given year");
        }

        $survey = Survey::findForTypePositionYear(Survey::ALPHA, Position::ALPHA, $year);
        $mentors = PersonMentor::where('mentor_year', $year)
            ->where('person_id', $personId)
            ->where('status', PersonMentor::PASS)
            ->with(['mentor:id,callsign,person_photo_id', 'mentor.person_photo'])
            ->get()
            ->map(function ($t) {
                $p = $t->mentor;
                return (object)[
                    'id' => $p->id,
                    'callsign' => $p->callsign,
                    'photo_url' => $p->person_photo->image_url ?? null,
                ];
            })->sortBy('callsign')
            ->values();

        return [$survey, $mentors];
    }

    /**
     * Retrieve the slot record, the survey for the type, position & year and the attending trainers
     *
     * @param string $type
     * @param int $slotId
     * @param int $personId
     * @return array
     */

    public static function retrieveMentoringSurvey(string $type, int $slotId, int $personId): array
    {
        $slot = Slot::findOrFail($slotId);
        $survey = Survey::findForTypePositionYear($type, $slot->position_id, $slot->begins->year);
        error_log("* SLOT " . json_encode($slot, JSON_PRETTY_PRINT));
        $targets = Timesheet::where('position_id', $survey->mentoring_position_id)
            ->whereRaw('on_duty BETWEEN DATE_SUB(?, INTERVAL 1 HOUR) AND DATE_ADD(?, INTERVAL 1 HOUR)', [$slot->begins, $slot->begins])
            ->with('person:id,callsign,person_photo_id')
            ->with('person.person_photo')
            ->get()
            ->map(function ($t) {
                $p = $t->person;
                return (object)[
                    'id' => $t->person_id,
                    'callsign' => $p->callsign,
                    'position_id' => $t->position_id,
                    'position_title' => $t->position->title ?? "Position #{$t->position_id}",
                    'photo_url' => $p->person_photo->image_url ?? null,
                ];
            })->sortBy('callsign')->values();

        error_log("* TARGET " . json_encode($targets, JSON_PRETTY_PRINT));
        return [$slot, $survey, $targets->toArray()];
    }


    /**
     * Build up a survey response. The response is broken up into two parts:
     * the venue/class responses (material presentation, exercise quality, etc.) and the trainer ratings and responses
     *
     * Trainer feedback is handled in two different ways:
     * 1) A trainer-on-trainer survey (not group, survey.type='trainer') will loop thru ALL survey questions with each trainer
     * 2) A student-on-trainer survey group (survey_group.type='trainer') thru all group questions with each trainer
     *
     * @param Survey $survey
     * @param int|null $trainerId (optional) if set report only on the trainer
     * @return array
     */

    public static function buildSurveyReports(Survey $survey, int|null $trainerId = null): array
    {
        $includePerson = Auth::user() ? Auth::user()->hasRole(Role::SURVEY_MANAGEMENT) : true;

        $slots = $survey->retrieveSlots();
        $surveyGroups = SurveyGroup::findAllForSurvey($survey->id);
        $questions = SurveyQuestion::findAllForSurvey($survey->id);
        $questionsByGroupId = $questions->groupBy('survey_group_id');

        $sql = SurveyAnswer::where('survey_id', $survey->id)
            ->with(['trainer:id,callsign', 'person:id,callsign']);

        if ($trainerId) {
            $sql->where('trainer_id', $trainerId);
        }

        $surveyAnswers = $sql->get();
        $answersByQuestionId = $surveyAnswers->groupBy('survey_question_id');

        // Find all the trainers
        $trainerIds = $surveyAnswers->where('trainer_id', '>', 0)->pluck('trainer_id')->unique()->values();

        if ($trainerIds->isEmpty()) {
            $trainers = [];
        } else {
            $trainers = Person::select('id', 'callsign', 'person_photo_id')
                ->whereIntegerInRaw('id', $trainerIds)
                ->with('person_photo')
                ->get();
        }

        $mainReport = (object)[
            'id' => 'main',
            'type' => SurveyGroup::TYPE_NORMAL,
        ];

        $reports = [];

        foreach ($surveyGroups as $group) {
            $questions = $questionsByGroupId->get($group->id);
            if (!$questions) {
                continue;
            }

            if ($trainerId) {
                // Skip group if no answers were given
                if (!$surveyAnswers->first(fn($a) => ($a->survey_group_id == $group->id && $a->trainer_id == $trainerId))) {
                    continue;
                }
            }

            if ($group->type == SurveyGroup::TYPE_NORMAL) {
                $report = $mainReport;
            } else {
                $report = (object)[
                    'id' => $group->getReportId(),
                    'type' => $group->type,
                ];
                $reports[] = $report;
            }

            switch ($group->type) {
                case SurveyGroup::TYPE_NORMAL:
                case SurveyGroup::TYPE_SEPARATE:
                    self::buildGroupReport($survey, $report, $slots, $questions, $answersByQuestionId, $trainerId, $includePerson);
                    break;
                case SurveyGroup::TYPE_TRAINER:
                    self::buildTrainerGroupReport($survey, $report, $trainers, $slots, $questions, $surveyAnswers, $includePerson);
                    break;
                case SurveyGroup::TYPE_SUMMARY:
                    self::buildSummaryGroupReport($report, $questions, $answersByQuestionId, $includePerson);
                    break;
            }
        }

        if (isset($mainReport->questions)) {
            // Add the main report on top
            array_unshift($reports, $mainReport);
        }

        return $reports;
    }

    /**
     * Build up a group report (either main or separate) by running through each training session and questions.
     * $report['questions'] = [[
     *    'id => Survey Question id
     *    'description' => the question text
     *    'type' => question type (ranking, options, text)
     *    'mean' => statistical mean(when type='ranking')
     *    'variance' => statistical variance (when type='ranking')
     *    'rating_count' => how many rating responses (when type='ranking')
     *    'distribution' => hash, key is rating, value is rating count (when type='ranking')
     *    'slots' => [[
     *       'slot_id' => Training session id (slot.id)
     *        'slot_description' => Training session title/description (slot.description)
     *        'slot_begins' => Datetime when session begins (slot.begins)
     *        'responses' => [[
     *           'answer' => the answer to life, the universe, and the survey question.
     *            'person' => [
     *               id'    => person.id. (0 means the callsign is unknown and imported from
     *                                      the original survey forms hosted on Safety Phil's website.)
     *               'callsign' => person.callsign, or imported from the original surveys
     *             ]
     *          ]] end of responses array
     *      ]] end of slots array
     * ]
     * $reports['summarized_ratings'=> [[
     *    Ratings collected across all slots responses and totalled
     *    'description' => survey question description (type='rating')
     *    'slots' => [[
     *       'slot_id' => Training session id (slot.id)
     *       'slot_description' => Training session title/description (slot.description)
     *       'slot_begins' => Datetime when session begins (slot.begins)
     *       'mean' => statistical mean
     *       'variance' => statistical variance
     *       'distribution' => hash, key is rating, value is rating count
     *       'rating_count' => how many ratings were given
     *      ]]
     *   ]]
     * ]
     * @param Survey $survey
     * @param $report
     * @param $slots
     * @param $questions
     * @param $answersByQuestionId
     * @param $trainerId
     * @param $includePerson
     */

    public static function buildGroupReport(Survey $survey, $report, $slots, $questions, $answersByQuestionId, $trainerId, $includePerson): void
    {
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
            $slotResponses = [];
            $isRating = ($question->type == SurveyQuestion::RATING) || $question->summarize_rating;
            $overallRatings = [];

            if ($isAlpha) {
                $alphaResponses = [];
                foreach ($answersForQuestion as $answer) {
                    if ($isRating) {
                        $overallRatings[] = (int)$answer->response;
                    } else if (!$question->summarize_rating) {
                        $alphaResponses[] = self::buildAnswer($answer, $question, $includePerson);
                    }
                }
            } else {
                $answersGroupBySlot = $answersForQuestion->groupBy('slot_id');

                foreach ($slots as $slot) {
                    $answersForSlot = $answersGroupBySlot->get($slot->id);
                    if ($trainerId && !$answersForSlot) {
                        // Don't bother if only a single trainer is being looked up.
                        continue;
                    }

                    $responses = [];
                    $ratings = [];
                    if ($answersForSlot) {
                        foreach ($answersForSlot as $answer) {
                            if ($isRating) {
                                $overallRatings[] = $ratings[] = (int)$answer->response;
                            } else if (!$question->summarize_rating) {
                                $responses[] = self::buildAnswer($answer, $question, $includePerson);
                            }
                        }
                    }

                    $slotResponse = [
                        'slot_id' => $slot->id,
                        'slot_begins' => (string)$slot->begins,
                        'slot_description' => $slot->description,
                    ];

                    if ($isRating) {
                        self::computeStatistics($ratings, $slotResponse);
                    } else {
                        $slotResponse['responses'] = $responses;
                    }
                    $slotResponses[] = $slotResponse;
                }
            }

            $responseType = $isAlpha ? 'responses' : 'slots';
            if ($question->summarize_rating) {
                if (!$isAlpha) {
                    self::sortStatistics($slotResponses);
                }
                $report->summarized_ratings[] = [
                    'description' => $question->description,
                    $responseType => $isAlpha ? $alphaResponses : $slotResponses,
                ];
            } else {
                $data = [
                    'id' => $question->id,
                    'type' => $question->type,
                    'description' => $question->description,
                    $responseType => $isAlpha ? $alphaResponses : $slotResponses,
                ];
                if ($isRating) {
                    self::computeStatistics($overallRatings, $data);
                }
                $report->questions[] = $data;
            }
        }
    }

    /**
     * Build up a trainer group report. Iterate through each trainer, then training session, and questions.
     *
     * $report['trainers'] = [[
     *    'trainer_id' => Trainer (person) id
     *     'callsign' => callsign
     *     'photo_url' => headshot URL
     *      'questions => [[   All the questions
     *         'id => Survey Question id
     *         'description' => the question text
     *         'type' => question type (ranking, options, text)
     *         'mean' => statistical mean(when type='ranking')
     *         'variance' => statistical variance (when type='ranking')
     *         'rating_count' => how many rating responses (when type='ranking')
     *         'distribution' => hash, key is rating, value is rating count (when type='ranking')
     *         'slots' => [[
     *            'slot_id' => Training session id (slot.id)
     *            'slot_description' => Training session title/description (slot.description)
     *            'slot_begins' => Datetime when session begins (slot.begins)
     *            'responses' => [[
     *               'answer' => the answer to life, the universe, and the survey question.
     *               'person' => [
     *                  'id'    => person.id. (0 means the callsign is unknown and imported from
     *                                      the original survey forms hosted on Safety Phil's website.)
     *                  'callsign' => person.callsign, or imported from the original surveys
     *               ]
     *            ]] end of responses array
     *          ]] end of slots array
     *        ]] end of questions array
     *   ]] end of trainers array
     * ]
     * $reports['summarized_ratings'] = [[
     *    Ratings collected across all slots responses and totalled
     *    'description' => survey question description (type='rating')
     *     'trainers' => [[
     *        'trainer_id' => Trainer id's (person.id)
     *        'callsign' => Trainer's callsign
     *           'mean' => statistical mean
     *           'variance' => statistical variance
     *           'rating_count' => how many rating responses
     *           'distribution' => hash, key is rating, value is rating count
     *      ]]
     *   ]]
     * ]
     *          '
     * @param Survey $survey
     * @param $reportResults
     * @param $trainers
     * @param $slots
     * @param $questions
     * @param $surveyAnswers
     * @param $includePerson
     */

    public static function buildTrainerGroupReport(Survey $survey, $reportResults, $trainers, $slots, $questions, $surveyAnswers, $includePerson): void
    {
        $trainerReports = [];
        $answersByTrainerId = $surveyAnswers->filter(fn($a) => ($a->trainer_id > 0))->groupBy('trainer_id');

        $questionSummaryRatings = [];

        $isAlpha = $survey->type == Survey::ALPHA;
        foreach ($trainers as $trainer) {
            $report = [
                'trainer_id' => $trainer->id,
                'callsign' => $trainer->callsign,
                'photo_url' => $trainer->person_photo->image_url ?? null
            ];

            $answers = $answersByTrainerId->get($trainer->id);
            if (!$answers) {
                continue;
            }
            $answersForTrainerGroupByQuestion = $answers->groupBy('survey_question_id');

            foreach ($questions as $question) {
                $slotResponses = [];
                $isRating = ($question->type == SurveyQuestion::RATING) || $question->summarize_rating;
                $overallRatings = [];
                $answersForQuestion = $answersForTrainerGroupByQuestion->get($question->id);

                if (!$answersForQuestion) {
                    continue;
                }

                if ($isAlpha) {
                    $alphaResponses = [];
                    $ratings = [];
                    foreach ($answersForQuestion as $answer) {
                        if ($isRating) {
                            $overallRatings[] = $ratings[] = (int)$answer->response;
                        } else if ($question->summarize_rating) {
                            continue;
                        }
                        $alphaResponses[] = self::buildAnswer($answer, $question, $includePerson);
                    }
                } else {
                    $answersGroupBySlot = $answersForQuestion->groupBy('slot_id');
                    foreach ($slots as $slot) {
                        $answersForSlot = $answersGroupBySlot->get($slot->id);
                        if (!$answersForSlot) {
                            continue;
                        }
                        $responses = [];
                        $ratings = [];
                        foreach ($answersForSlot as $answer) {
                            if ($isRating) {
                                $overallRatings[] = $ratings[] = (int)$answer->response;
                            } else if ($question->summarize_rating) {
                                continue;
                            }

                            $responses[] = self::buildAnswer($answer, $question, $includePerson);
                        }

                        $slotResponse = [
                            'slot_id' => $slot->id,
                            'slot_begins' => (string)$slot->begins,
                            'slot_description' => $slot->description,
                        ];

                        if ($isRating) {
                            self::computeStatistics($ratings, $slotResponse);
                        } else {
                            $slotResponse['responses'] = $responses;
                        }
                        $slotResponses[] = $slotResponse;
                    }
                }

                if ($question->summarize_rating) {
                    $summary = [
                        'trainer_id' => $trainer->id,
                        'callsign' => $trainer->callsign,
                        'ratings' => $overallRatings
                    ];
                    self::computeStatistics($overallRatings, $summary);
                    $questionSummaryRatings[$question->id][] = $summary;
                } else {
                    $data = [
                        'id' => $question->id,
                        'type' => $question->type,
                        'description' => $question->description,
                    ];

                    if ($isAlpha) {
                        $data['responses'] = $alphaResponses;
                    } else {
                        $data['slots'] = $slotResponses;
                    }
                    if ($isRating) {
                        self::computeStatistics($overallRatings, $data);
                    }
                    $report['questions'][] = $data;
                }
            }
            $trainerReports[] = $report;
        }

        if (!empty($questionSummaryRatings)) {
            $summarizedRatings = [];
            foreach ($questionSummaryRatings as $questionId => $summary) {
                $question = $questions->firstWhere('id', $questionId);
                self::sortStatistics($summary);
                $summarizedRatings[] = [
                    'description' => $question->description,
                    'trainers' => $summary
                ];
            }
            $reportResults->summarized_ratings = $summarizedRatings;
        }

        usort($trainerReports, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));
        $reportResults->trainers = $trainerReports;
    }

    /**
     * Build up a summary group - run through all the responses and
     * group together into one big report. (NOT broken down by slot)
     *
     * @param $report
     *   $report['questions'][] - all the questions
     *              ['description'] string, the question text
     *              ['type'] string, question type (rating, option, text)
     *              ['responses'][] - all the responses for not-rating type
     *                  ['answer'] - the answer
     *                  ['person']['id', 'callsign'] - the person responding.
     *              ['mean','variant','distribution','rating_count'] - if rating type
     *
     * @param $questions
     * @param $answersByQuestionId
     * @param $includePerson
     */

    public static function buildSummaryGroupReport($report, $questions, $answersByQuestionId, $includePerson): void
    {
        $questionResponses = [];
        foreach ($questions as $question) {
            $answers = $answersByQuestionId->get($question->id);
            if (!$answers) {
                continue;
            }

            $ratings = [];
            $responses = [];
            $isRating = ($question->type == SurveyQuestion::RATING);
            foreach ($answers as $answer) {
                if ($isRating) {
                    $ratings[] = (int)$answer->response;
                } else {
                    $responses[] = self::buildAnswer($answer, $question, $includePerson);
                }
            }

            $questionResponse = ['description' => $question->description, 'type' => $question->type];
            if ($isRating) {
                self::computeStatistics($ratings, $questionResponse);
            } else {
                $questionResponse['responses'] = $responses;
            }
            $questionResponses[] = $questionResponse;
        }

        $report->questions = $questionResponses;
    }


    /**
     * Compute the statistics (mean, variance, distribution) for a rating array
     *
     * @param array $ratings An array of integers
     * @param array $response The stats returned
     */

    public static function computeStatistics(array $ratings, array &$response): void
    {
        $sum = array_sum($ratings);
        $count = count($ratings);
        $distribution = [];
        if ($count) {
            $mean = $sum / $count;
            $stdDev = 0;
            foreach ($ratings as $rating) {
                $stdDev += ($rating - $mean) * ($rating - $mean);
                $distribution[$rating] = ($distribution[$rating] ?? 0) + 1;
            }
            $variance = $stdDev / $count;
            ksort($distribution);
        } else {
            $mean = 0.0;
            $variance = 0.0;
        }

        $response['mean'] = round($mean, 1);
        $response['variance'] = round($variance, 1);
        $response['distribution'] = $distribution;
        $response['rating_count'] = $count;
    }

    /**
     * Sort responses based on the stats. Mean in descending order, then rating responses
     * $stats[]['mean'] - mean to sort on
     * $stats[]['rating_count'] - how many ratings were given.
     *
     * @param $stats
     */

    public static function sortStatistics(&$stats): void
    {
        usort($stats, function ($a, $b) {
            $diff = $b['mean'] - $a['mean'];
            if (!$diff) {
                return $b['rating_count'] - $a['rating_count'];
            } else {
                return $diff > 0 ? 1 : -1;
            }
        });
    }

    /**
     * Build the answer with the respondent's name (optional).
     *
     * Answers imported into the Clubhouse for surveys 2019 and earlier, there may not
     * an actually person id, the callsign field on the survey form was not validated.
     * In the case where person_id is 0, it means the callsign could not be associated with
     * a Clubhouse account.
     *
     * @param SurveyAnswer $answer
     * @param SurveyQuestion $question
     * @param bool $includePerson
     * @return array
     */

    public static function buildAnswer(SurveyAnswer $answer, SurveyQuestion $question, bool $includePerson): array
    {
        if ($includePerson || $answer->can_share_name) {
            if ($answer->person_id && $answer->person) {
                $person = [
                    'id' => $answer->person->id,
                    'callsign' => $answer->person->callsign
                ];
            } else {
                $person = [
                    'id' => 0,
                    'callsign' => $answer->callsign
                ];
            }
            return [
                'answer' => $answer->response,
                'person' => $person
            ];

        }

        return ['answer' => $answer->response];
    }

    /**
     * Report on a trainer for a given year
     *
     * @param int $trainerId
     * @param int $year
     * @param string|null $type
     * @return array
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
                'reports' => SurveyReports::buildSurveyReports($survey, $trainerId)
            ];
        }

        return $surveyReports;
    }

    /**
     * Report on all trainers who have feedback for a given survey
     *
     * @param Survey $survey
     * @return array
     */

    public static function allTrainersReport(Survey $survey): array
    {
        // Find all the trainers with feedback for a given survey
        $foundTrainers = SurveyAnswer::join('person', 'survey_answer.trainer_id', 'person.id')
            ->select('trainer_id', 'person.callsign')
            ->where('survey_id', $survey->id)
            ->groupBy('trainer_id', 'person.callsign')
            ->with(['trainer.person_photo'])
            ->get();

        $trainers = [];
        foreach ($foundTrainers as $trainer) {
            $trainers[] = [
                'id' => $trainer->trainer_id,
                'callsign' => $trainer->callsign,
                'photo_url' => $trainer->trainer->person_photo->image_url ?? null,
                'report' => SurveyReports::buildSurveyReports($survey, $trainer->trainer_id)[0],
            ];
        }

        usort($trainers, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));

        return $trainers;
    }
}
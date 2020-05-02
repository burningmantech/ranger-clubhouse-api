<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;

use App\Models\Role;
use App\Models\Person;
use App\Models\Slot;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyGroup;
use App\Models\SurveyQuestion;
use App\Models\TraineeStatus;
use App\Models\TrainerStatus;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;


class SurveyController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index()
    {
        $params = request()->validate([
            'year' => 'required|integer',
            'position_id' => 'sometimes|integer',
            'include_slots' => 'sometimes|boolean',
        ]);

        /*
         * TODO: validate on parameters wrt roles
         */

        $this->authorize('index', Survey::class);
        return $this->success(Survey::findForQuery($params), null, 'survey');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function store()
    {
        $this->authorize('store', [Survey::class]);
        $survey = new Survey;
        $this->fromRest($survey);

        if ($survey->save()) {
            return $this->success($survey);
        }

        return $this->restError($survey);
    }

    /**
     * Display the specified resource.
     *
     * Survey $survey
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(Survey $survey)
    {
        $this->authorize('show', [Survey::class]);

        return $this->success($survey);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Survey $survey
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function update(Survey $survey)
    {
        $this->authorize('update', $survey);
        $this->fromRest($survey);

        if ($survey->save()) {
            return $this->success($survey);
        }

        return $this->restError($survey);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Survey $survey
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function destroy(Survey $survey)
    {
        $this->authorize('destroy', $survey);
        $survey->delete();

        foreach (['survey_group', 'survey_question', 'survey_answer'] as $table) {
            DB::table($table)->where('survey_id', $survey->id)->delete();
        }
        return $this->restDeleteSuccess();
    }

    /**
     * Clone/duplicate a survey (and dependent tables) set to the current year
     * @param Survey $survey
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function duplicate(Survey $survey)
    {
        $this->authorize('duplicate', $survey);

        $year = current_year();

        $surveyId = $survey->id;
        $survey->load(['survey_groups', 'survey_groups.survey_questions']);
        $newSurvey = $survey->replicate();
        $newSurvey->year = $year;
        // Update the title to the current year
        $newSurvey->title = str_replace((string)$survey->year, (string)$year, $survey->title);
        $newSurvey->saveOrThrow();
        $newSurveyId = $newSurvey->id;

        try {
            foreach ($survey->survey_groups as $group) {
                $newGroup = $group->replicate();
                $newGroup->survey_id = $newSurveyId;
                $newGroup->saveOrThrow();
                $newGroupId = $newGroup->id;

                foreach ($group->survey_questions as $question) {
                    $newQ = $question->replicate();
                    $newQ->survey_id = $newSurveyId;
                    $newQ->survey_group_id = $newGroupId;
                    $newQ->saveOrThrow();
                }
            }
        } catch (Exception $e) {
            $newSurvey->delete();
            SurveyGroup::where('survey_id', $newSurveyId)->delete();
            SurveyQuestion::where('survey_id', $newSurveyId)->delete();
            throw $e;
        }

        return response()->json(['status' => 'success', 'survey_id' => $newSurveyId]);
    }

    /**
     * Prepare a questionnaire
     *
     * @param Survey $survey
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function questionnaire()
    {
        $params = request()->validate([
            'slot_id' => 'required|integer',
            'type' => [
                'required',
                'string',
                Rule::in([Survey::TRAINING, Survey::TRAINER])
            ]
        ]);


        list ($slot, $survey, $trainers) = $this->retrieveSlotSurveyTrainers($params['type'], $params['slot_id']);

        return response()->json(['survey' => $survey, 'trainers' => $trainers, 'slot' => $slot]);
    }

    /**
     * Retrieve the slot record, the survey for the type, position & year and the attending trainers
     *
     * @param string $type
     * @param int $slotId
     * @return array
     */

    private function retrieveSlotSurveyTrainers(string $type, int $slotId) : array
    {
        $slot = Slot::findOrFail($slotId);
        $personId = $this->user->id;

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
                    throw new \InvalidArgumentException('You are not marked as having attended the training session.');
                }
                break;

            case Survey::TRAINER:
                $me = $trainers->firstWhere('id', $personId);
                if (!$me) {
                    throw new \InvalidArgumentException('You are not marked as having taught the training session.');
                }
                // Filter out the trainer
                $trainers = $trainers->where('id', '!=', $personId);
                break;

            default:
                throw new \InvalidArgumentException("Unknown survey type [$type]");
        }

        return [$slot, $survey, $trainers->values()];
    }

    /**
     * Submit a survey response
     *
     * @return JsonResponse
     * @throws InvalidArgumentException
     */

    public function submit()
    {
        $params = request()->validate([
            'slot_id' => 'required|integer',
            'type' => [
                'required',
                'string',
                Rule::in([Survey::TRAINING, Survey::TRAINER])
            ],
            'survey.*.survey_group_id' => 'required|integer',
            'survey.*.trainer_id' => 'sometimes',
            'survey.*.can_share_name' => 'sometimes|boolean',
            'survey.*.answers.*.survey_question_id' => 'required|integer',
            'survey.*.answers.*.response' => 'present',
        ]);

        list ($slot, $survey, $trainers) = $this->retrieveSlotSurveyTrainers($params['type'], $params['slot_id']);

        $personId = $this->user->id;
        $slotId = $slot->id;

        SurveyAnswer::deleteAllForPersonSlot($survey->id, $personId, $slotId);

        $isTrainerSurvey = ($survey->type == Survey::TRAINER);

        // Loop through each survey answer group
        foreach ($params['survey'] as $group) {
            $surveyGroup = SurveyGroup::findOrFail($group['survey_group_id']);
            if ($surveyGroup->survey_id != $survey->id) {
                throw new \InvalidArgumentException("Survey group [{$surveyGroup->id}] is not part of the survey");
            }

            // A trainer survey asks if the responder's name can be shared with the trainer
            if ($isTrainerSurvey) {
                $canShareName = $group['can_share_name'] ?? true;
            } else {
                $canShareName = true;
            }

            $trainerId = $group['trainer_id'] ?? null;

            foreach ($group['answers'] as $answer) {
                SurveyAnswer::create([
                    'person_id' => $personId,
                    'survey_id' => $survey->id,
                    'survey_group_id' => $surveyGroup->id,
                    'survey_question_id' => $answer['survey_question_id'],
                    'slot_id' => $slotId,
                    'trainer_id' => $trainerId,
                    'response' => $answer['response'],
                    'can_share_name' => $canShareName,
                ]);
            }
        }

        return $this->success();
    }

    /**
     * Locate all the surveys with feedback for the trainer
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function trainerSurveys()
    {
        $params = request()->validate([
            'person_id' => 'required|integer'
        ]);

        $personId = $params['person_id'];

        $this->authorize('trainerSurveys', [Survey::class, $personId]);
        $surveys = Survey::retrieveAllForTrainer($personId);
        return response()->json(['surveys' => $surveys]);
    }

    /**
     * Generate a trainer feedback report for a given year
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function trainerReport()
    {
        $params = request()->validate([
            'trainer_id' => 'required|integer|exists:person,id',
            'year' => 'required|integer'
        ]);

        $trainerId = $params['trainer_id'];
        $year = $params['year'];

        $this->authorize('trainerReport', [Survey::class, $trainerId]);
        $surveys = Survey::findAllForPersonYear($trainerId, $year);

        $surveyReports = [];
        foreach ($surveys as $survey) {
            $response = $this->buildSurveyReport($survey, $trainerId);
            $surveyReports[] = [
                'id' => $survey->id,
                'year' => $survey->year,
                'type' => $survey->type,
                'title' => $survey->title,
                'position_id' => $survey->position_id,
                'position_title' => $survey->position->title,
                'responses' => $response['trainer_responses'][0]['responses']
            ];
        }

        return response()->json(['surveys' => $surveyReports]);
    }

    /**
     * Build up a survey response. The response is broken up into two parts:
     * the venue/class responses (material presentation, exercise quality, etc.) and the trainer ratings and responses
     *
     * Trainer feedback is handled in two different ways:
     * 1) A trainer survey (survey.type == "trainer") will loop thru ALL survey questions with each trainer
     * 2) A survey answer group with is_trainer_group set will loop thru all group questions with each trainer
     *
     * @param Survey $survey
     * @param null $trainerId (optional) if set only report on one specific trainer
     * @return array[]
     */

    private function buildSurveyReport(Survey $survey, $trainerId = null)
    {
        $slots = $survey->retrieveSlots();
        $slotsById = $slots->keyBy('id');

        $surveyGroups = SurveyGroup::findAllForSurvey($survey->id)->keyBy('id');
        $questions = SurveyQuestion::findAllForSurvey($survey->id);

        $sql = SurveyAnswer::where('survey_id', $survey->id)
            ->with(['trainer:id,callsign', 'person:id,callsign']);

        if ($trainerId) {
            $sql->where('trainer_id', $trainerId);
        }

        $surveyAnswers = $sql->get();
        $answersBySlotQuestion = $surveyAnswers->groupBy([ 'slot_id', 'survey_question_id' ]);

        /*
         * When looping through each training session, only report on those trainers
         * that have responses for said session.
         */
        $slotAnswersByTrainer = [];
        foreach ($surveyAnswers as $a) {
            if (!$a->trainer_id) {
                continue;
            }

            if (!isset($slotAnswersByTrainer[$a->trainer_id])) {
                $slotAnswersByTrainer[$a->trainer_id] = [];
            }
            $slotAnswersByTrainer[$a->trainer_id][$a->slot_id] = 1;
        }

        $responsesByQuestion = [];
        $trainersById = [];

        $isTrainerSurvey = ($survey->type == Survey::TRAINER);

        // Find all the trainers
        $trainerIds = $surveyAnswers->where('trainer_id', '>', 0)->pluck('trainer_id')->unique()->values();

        if ($trainerIds->isEmpty()) {
            $trainers = [];
        } else {
            $trainers = Person::select('id', 'callsign')->whereIn('id', $trainerIds)->get();
        }

        /*
         * Loop through each trainer, and setup the slot and possible question responses
         */
        foreach ($trainers as $t) {
            $slotResponses = [];
            foreach ($slots as $s) {
                // Only report on trainers with feedback for a given session
                if (isset($slotAnswersByTrainer[$t->id][$s->id])) {
                    $slotResponses[$s->id] = [];
                }
            }

            $questionResponses = [];
            foreach ($questions as $q) {
                $sg = $surveyGroups[$q->survey_group_id];
                if ($sg->is_trainer_group || $isTrainerSurvey) {
                    $questionResponses[$q->id] = [
                        'question' => $q,
                        'slots' => $slotResponses
                    ];
                }
            }

            $trainersById[$t->id] = [
                'person' => (object)[ 'id' => $t->id,  'callsign' => $t->callsign ],
                'questions' => $questionResponses
            ];
        }

        // Setup to report on each session (aka slot)
        foreach ($questions as $q) {
            $slotResponses = [];
            foreach ($slots as $s) {
                $slotResponses[$s->id] = [];
            }

            $responsesByQuestion[$q->id] = [
                'question' => $q,
                'slots' =>  $slotResponses
            ];
        }

        /*
         * Loop through each slot, all the questions, and each question answer to sort out venue and trainer
         * responses
         */
        foreach ($slots as $slot) {
            foreach ($questions as $q) {
                $sg = $surveyGroups[$q->survey_group_id];
                $slotAnswers = $answersBySlotQuestion[$slot->id][$q->id] ?? null;
                if (!$slotAnswers) {
                    // The questions has no answers!
                    continue;
                }
                foreach ($slotAnswers as $answer) {
                    if ($sg->is_trainer_group || $isTrainerSurvey) {
                        // A trainer!
                        $trainersById[$answer->trainer_id]['questions'][$q->id]['slots'][$answer->slot_id][] = $answer;
                    } else {
                        $responsesByQuestion[$q->id]['slots'][$answer->slot_id][] = $answer;
                    }
                }
            }
        }

        $response = [
            'trainer_responses' => $this->buildTrainerResponses($trainersById, $slotsById, $isTrainerSurvey)
        ];

        if (!$trainerId) {
            $response['venue_responses'] = $this->buildVenueResponses($responsesByQuestion, $slotsById);
        }

        return $response;
    }

    /**
     * Build up the trainer responses
     *
     * @param $trainersById
     * @param $slotsById
     * @param $isTrainerSurvey
     * @return array
     */

    private function buildTrainerResponses($trainersById, $slotsById, $isTrainerSurvey) : array
    {
        $trainers = [];
        $hasSurveyRole = $this->userHasRole(Role::SURVEY_MANAGEMENT);

        foreach ($trainersById as $trainerId => $trainer) {
            $trainerResponses = [];
            foreach ($trainer['questions'] as $questionId => $response) {
                 $question = $response['question'];

                $slots = [];
                $slotResponse = [
                    'type' => $question->type,
                    'code' => $question->code,
                    'id' => $question->id,
                    'description' => $question->description,
                ];

                $isRating = ($question->type == SurveyQuestion::RATING);
                $overallRatings = [];

                foreach ($response['slots'] as $slotId => $answers) {
                    $ratings = [];
                    $slotAnswers = [];
                    foreach ($answers as $answer) {
                        if ($isRating) {
                            $rating = (int)$answer->response;
                            $ratings[] = $rating;
                            $overallRatings[] = $rating;
                        }

                        $data = ['response' => $answer->response];

                        if ($hasSurveyRole || !$isTrainerSurvey || $answer->can_share_name) {
                            $person = $answer->person ?? null;
                            $data['id'] = $answer->person_id;
                            $data['callsign'] = $person ? $person->callsign : $answer->callsign;
                        }

                        $slotAnswers[] = $data;
                    }
                    $slot = $slotsById[$slotId];
                    $sr = [
                        'id' => $slotId,
                        'begins' => (string)$slot->begins,
                        'description' => $slot->description,
                        'signed_up' => $slot->signed_up,
                        'answers' => $slotAnswers
                    ];
                    if ($isRating) {
                        // Compute the rating for all question responses
                        self::computeStats($ratings, $sr);
                    }
                    $slots[] = $sr;
                }

                if ($isRating) {
                    self::computeStats($overallRatings, $slotResponse);
                }

                $slotResponse['slots'] = $slots;
                $trainerResponses[$question->code] = $slotResponse;
            }

            $person = $trainer['person'];
            $trainers[] = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'responses' => $trainerResponses
            ];
        }

        usort($trainers, function ($a, $b) {
            return strcasecmp($a['callsign'], $b['callsign']);
        });

        return $trainers;
    }

    /**
     * Compute the statistics (mean, variance, distribution) for a rating array
     *
     * @param array $ratings An array of integers
     * @param array $response The stats returned
     */

    private function computeStats(array $ratings, array &$response)
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
     * Build up the venue/session responses
     *
     * @param $responsesByQuestion
     * @param $slotsById
     * @return array
     */

    private function buildVenueResponses($responsesByQuestion, $slotsById)
    {
        $venueResponses = [];

        foreach ($responsesByQuestion as $questionId => $response) {
            $question = $response['question'];

            $slots = [];
            $slotResponse = [
                'type' => $question->type,
                'code' => $question->code,
                'id' => $question->id,
                'description' => $question->description,
            ];

            $isRating = ($question->type == SurveyQuestion::RATING);

            $overallRatings = [];
            foreach ($response['slots'] as $slotId => $answers) {
                $ratings = [];
                $slotAnswers = [];
                foreach ($answers as $answer) {
                    if ($isRating) {
                        $rating = (int)$answer->response;
                        $ratings[] = $rating;
                        $overallRatings[] = $rating;
                    }

                    $person = $answer->person ?? null;
                    $slotAnswers[] = [
                        'id' => $answer->person_id,
                        'callsign' => $person ? $person->callsign : $answer->callsign,
                        'response' => $answer->response,
                    ];
                }
                $slot = $slotsById[$slotId];
                $sr = [
                    'id' => $slotId,
                    'begins' => (string)$slot->begins,
                    'description' => $slot->description,
                    'signed_up' => $slot->signed_up,
                    'answers' => $slotAnswers
                ];
                if ($isRating) {
                    // Compute the rating for all question responses
                    self::computeStats($ratings, $sr);
                }
                $slots[] = $sr;
            }
            if ($isRating) {
                self::computeStats($overallRatings, $slotResponse);
            }

            $slotResponse['slots'] = $slots;
            $venueResponses[$question->code] = $slotResponse;
        }

        return $venueResponses;
    }

    /**
     * Generate a trainer report for ALL trainers.
     *
     * @param Survey $survey
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function allTrainersReport(Survey $survey)
    {
        $this->authorize('allTrainersReport', $survey);

        if ($survey->type != Survey::TRAINER) {
            throw new \InvalidArgumentException("Survey is not a trainer-for-trainer survey");
        }

        // Find all the trainers with feedback for a given survey
        $foundTrainers = SurveyAnswer::join('person', 'survey_answer.trainer_id', 'person.id')
            ->select('trainer_id', 'person.callsign')
            ->where('survey_id', $survey->id)
            ->groupBy('trainer_id', 'person.callsign')
            ->with(['trainer.person_photo'])
            ->get();

        $trainers = [];
        foreach ($foundTrainers as $trainer) {
            $response = $this->buildSurveyReport($survey, $trainer->trainer_id);
            $trainers[] = [
                'id' => $trainer->trainer_id,
                'callsign' => $trainer->callsign,
                'photo_url' => $trainer->trainer->person_photo->image_url ?? null,
                'responses' => $response['trainer_responses'][0]['responses']
            ];
        }

        usort($trainers, function ($a, $b) {
            return strcasecmp($a['callsign'], $b['callsign']);
        });

        return response()->json(['trainers' => $trainers]);
    }

    /**
     * Generate a Survey report
     *
     * @param Survey $survey
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function report(Survey $survey)
    {
        $this->authorize('report', $survey);
        return response()->json($this->buildSurveyReport($survey));
    }

}

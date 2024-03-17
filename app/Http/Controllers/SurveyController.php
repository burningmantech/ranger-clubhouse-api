<?php

namespace App\Http\Controllers;

use App\Lib\SurveyReports;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyGroup;
use App\Models\SurveyQuestion;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Exceptions\UnacceptableConditionException;

class SurveyController extends ApiController
{
    /**
     * Retrieve a list of surveys
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $params = request()->validate([
            'year' => 'sometimes|integer',
            'position_id' => 'sometimes|integer',
            'include_slots' => 'sometimes|boolean',
            'type' => 'sometimes|string',
        ]);

        /*
         * TODO: validate on parameters wrt roles
         */

        $this->authorize('index', Survey::class);

        return $this->success(Survey::findForQuery($params), null, 'survey');
    }

    /**
     * Create a survey
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', Survey::class);
        $survey = new Survey;
        $this->fromRest($survey);

        if ($survey->save()) {
            return $this->success($survey);
        }

        return $this->restError($survey);
    }

    /**
     * Show a survey
     *
     * Survey $survey
     * @param Survey $survey
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(Survey $survey): JsonResponse
    {
        $this->authorize('show', $survey);
        return $this->success($survey);
    }

    /**
     * Update a survey
     *
     * @param Survey $survey
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */
    public function update(Survey $survey): JsonResponse
    {
        $this->authorize('update', $survey);
        $this->fromRest($survey);

        if ($survey->save()) {
            return $this->success($survey);
        }

        return $this->restError($survey);
    }

    /**
     * Delete a survey
     *
     * @param Survey $survey
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function destroy(Survey $survey): JsonResponse
    {
        $this->authorize('destroy', $survey);
        $survey->delete();
        return $this->restDeleteSuccess();
    }

    /**
     * Clone/duplicate a survey (and dependent tables) set to the current year
     *
     * @param Survey $survey
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function duplicate(Survey $survey): JsonResponse
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
     * @return JsonResponse
     */

    public function questionnaire(): JsonResponse
    {
        $params = request()->validate([
            'type' => [
                'required',
                'string',
                Rule::in([Survey::TRAINING, Survey::TRAINER, Survey::ALPHA])
            ],
            'slot_id' => [
                'integer',
                'required_unless:type,' . Survey::ALPHA
            ],
            'year' => [
                'integer',
                'required_if:type,' . Survey::ALPHA
            ],
        ]);

        if ($params['type'] == Survey::ALPHA) {
            list ($survey, $trainers) = SurveyReports::retrieveAlphaSurvey($params['year'], $this->user->id);
            return response()->json(['survey' => $survey, 'trainers' => $trainers]);
        }

        list ($slot, $survey, $trainers) = SurveyReports::retrieveSlotSurveyTrainers($params['type'], $params['slot_id'], $this->user->id);

        return response()->json(['survey' => $survey, 'trainers' => $trainers, 'slot' => $slot]);
    }

    /**
     * Submit a survey response
     *
     * @return JsonResponse
     * @throws UnacceptableConditionException
     * @throws ValidationException
     */

    public function submit(): JsonResponse
    {
        $params = request()->validate([
            'type' => [
                'required',
                'string',
                Rule::in([Survey::TRAINING, Survey::TRAINER, Survey::ALPHA])
            ],
            'slot_id' => [
                'integer',
                'required_unless:type,' . Survey::ALPHA
            ],
            'year' => [
                'integer',
                'required_if:type,' . Survey::ALPHA
            ],
            'survey.*.survey_group_id' => 'required|integer',
            'survey.*.trainer_id' => 'sometimes',
            'survey.*.can_share_name' => 'sometimes|boolean',
            'survey.*.answers.*.survey_question_id' => 'required|integer',
            'survey.*.answers.*.response' => 'present',
        ]);

        $type = $params['type'];

        $slot = null;
        $survey = null;
        $trainers = null;
        $year = $params['year'] ?? null;
        $personId = $this->user->id;

        if ($type == Survey::ALPHA) {
            [$survey, $trainers] = SurveyReports::retrieveAlphaSurvey($year, $personId);
            $slotId = null;
        } else {
            [$slot, $survey, $trainers] = SurveyReports::retrieveSlotSurveyTrainers($type, $params['slot_id'], $personId);
            $slotId = $slot->id;
        }


        if ($type == Survey::ALPHA) {
            SurveyAnswer::deleteAllForSurvey($survey->id, $personId);
        } else {
            SurveyAnswer::deleteAllForPersonSlot($survey->id, $personId, $slotId);

        }

        $isTrainerSurvey = ($survey->type == Survey::TRAINER);

        try {
            DB::beginTransaction();
            // Loop through each survey answer group
            foreach ($params['survey'] as $group) {
                $surveyGroup = SurveyGroup::findOrFail($group['survey_group_id']);
                if ($surveyGroup->survey_id != $survey->id) {
                    throw new UnacceptableConditionException("Survey group [{$surveyGroup->id}] is not part of the survey");
                }

                // A trainer survey asks if the responder's name can be shared with the trainer
                if ($isTrainerSurvey) {
                    $canShareName = $group['can_share_name'] ?? true;
                } else {
                    $canShareName = true;
                }

                $trainerId = $group['trainer_id'] ?? null;
                $answers = $group['answers'] ?? [];

                foreach ($answers as $answer) {
                    $a = new SurveyAnswer([
                        'can_share_name' => $canShareName,
                        'person_id' => $personId,
                        'response' => $answer['response'],
                        'slot_id' => $slotId,
                        'survey_group_id' => $surveyGroup->id,
                        'survey_id' => $survey->id,
                        'survey_question_id' => $answer['survey_question_id'],
                        'trainer_id' => $trainerId,
                    ]);
                    $a->saveOrThrow();
                }
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            throw $e;
        }

        return $this->success();
    }

    /**
     * Locate all the surveys with feedback for the trainer
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function trainerSurveys(): JsonResponse
    {
        $params = request()->validate([
            'person_id' => 'required|integer'
        ]);

        $personId = $params['person_id'];

        $this->authorize('trainerSurveys', [Survey::class, $personId]);
        return response()->json(['surveys' => Survey::retrieveAllForTrainer($personId)]);
    }

    /**
     * Generate a trainer (or mentor) feedback report for a given year
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function trainerReport(): JsonResponse
    {
        $params = request()->validate([
            'trainer_id' => 'required|integer|exists:person,id',
            'year' => 'required|integer',
            'type' => 'sometimes|string',
        ]);

        $trainerId = $params['trainer_id'];

        $this->authorize('trainerReport', [Survey::class, $trainerId]);

        return response()->json([
            'surveys' => SurveyReports::trainerReportForYear($trainerId, $params['year'], $params['type'] ?? null)
        ]);
    }


    /**
     * Generate a trainer report for ALL trainers.
     *
     * @param Survey $survey
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function allTrainersReport(Survey $survey): JsonResponse
    {
        $this->authorize('allTrainersReport', $survey);

        if ($survey->type != Survey::TRAINER) {
            throw new UnacceptableConditionException("Survey is not a trainer-for-trainer survey");
        }

        return response()->json(['trainers' => SurveyReports::allTrainersReport($survey)]);
    }

    /**
     * Generate a Survey report
     *
     * @param Survey $survey
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function report(Survey $survey): JsonResponse
    {
        $this->authorize('report', $survey);
        return response()->json(['reports' => SurveyReports::buildSurveyReports($survey)]);
    }

}

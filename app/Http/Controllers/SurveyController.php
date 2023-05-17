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
use InvalidArgumentException;

class SurveyController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
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
     * @throws AuthorizationException|ValidationException
     */

    public function store(): JsonResponse
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
     * @param Survey $survey
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(Survey $survey): JsonResponse
    {
        $this->authorize('show', [Survey::class]);

        return $this->success($survey);
    }

    /**
     * Update the specified resource in storage.
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
     * Remove the specified resource from storage.
     *
     * @param Survey $survey
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function destroy(Survey $survey): JsonResponse
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
            'slot_id' => 'required|integer',
            'type' => [
                'required',
                'string',
                Rule::in([Survey::TRAINING, Survey::TRAINER])
            ]
        ]);


        list ($slot, $survey, $trainers) = SurveyReports::retrieveSlotSurveyTrainers($params['type'], $params['slot_id'], $this->user->id);

        return response()->json(['survey' => $survey, 'trainers' => $trainers, 'slot' => $slot]);
    }

    /**
     * Submit a survey response
     *
     * @return JsonResponse
     * @throws InvalidArgumentException
     */

    public function submit(): JsonResponse
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

        list ($slot, $survey, $trainers) = SurveyReports::retrieveSlotSurveyTrainers($params['type'], $params['slot_id'], $this->user->id);

        $personId = $this->user->id;
        $slotId = $slot->id;

        SurveyAnswer::deleteAllForPersonSlot($survey->id, $personId, $slotId);

        $isTrainerSurvey = ($survey->type == Survey::TRAINER);

        // Loop through each survey answer group
        foreach ($params['survey'] as $group) {
            $surveyGroup = SurveyGroup::findOrFail($group['survey_group_id']);
            if ($surveyGroup->survey_id != $survey->id) {
                throw new InvalidArgumentException("Survey group [{$surveyGroup->id}] is not part of the survey");
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
     * Generate a trainer feedback report for a given year
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function trainerReport(): JsonResponse
    {
        $params = request()->validate([
            'trainer_id' => 'required|integer|exists:person,id',
            'year' => 'required|integer'
        ]);

        $trainerId = $params['trainer_id'];
        $year = $params['year'];

        $this->authorize('trainerReport', [Survey::class, $trainerId]);

        return response()->json(['surveys' => SurveyReports::trainerReportForYear($trainerId, $year)]);
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
            throw new InvalidArgumentException("Survey is not a trainer-for-trainer survey");
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

<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class SurveyQuestionController extends ApiController
{
    /**
     * Retrieve a listing of survey questions
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $params = request()->validate([
            'survey_id' => 'required|integer:exists:survey_group,id'
        ]);

        $survey = Survey::findOrFail($params['survey_id']);
        $this->authorize('index', [SurveyQuestion::class, $survey]);
        return $this->success(SurveyQuestion::findAllForSurvey($params['survey_id']), null, 'survey_question');
    }

    /**
     * Create a survey question
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function store(): JsonResponse
    {
        $surveyQuestion = new SurveyQuestion;
        $this->fromRest($surveyQuestion);
        $this->authorize('store', $surveyQuestion);

        if ($surveyQuestion->save()) {
            return $this->success($surveyQuestion);
        }

        return $this->restError($surveyQuestion);
    }

    /**
     * Show a survey question
     *
     * @param SurveyQuestion $surveyQuestion
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(SurveyQuestion $surveyQuestion): JsonResponse
    {
        $this->authorize('show', $surveyQuestion);
        return $this->success($surveyQuestion);
    }

    /**
     * Update a survey question
     *
     * @param SurveyQuestion $surveyQuestion
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function update(SurveyQuestion $surveyQuestion): JsonResponse
    {
        $this->authorize('update', $surveyQuestion);
        $this->fromRest($surveyQuestion);

        if ($surveyQuestion->save()) {
            return $this->success($surveyQuestion);
        }

        return $this->restError($surveyQuestion);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param SurveyQuestion $surveyQuestion
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(SurveyQuestion $surveyQuestion): JsonResponse
    {
        $this->authorize('destroy', $surveyQuestion);
        $surveyQuestion->delete();

        return $this->restDeleteSuccess();
    }
}

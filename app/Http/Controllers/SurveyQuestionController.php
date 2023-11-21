<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

use Illuminate\Support\Facades\DB;

use App\Models\SurveyQuestion;

use App\Http\Controllers\ApiController;
use Illuminate\Validation\ValidationException;

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

        $this->authorize('index', SurveyQuestion::class);
        return $this->success(SurveyQuestion::findAllForSurvey($params['survey_id']), null, 'survey_question');
    }

    /**
     * Create a survey question
     *
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws ValidationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', SurveyQuestion::class);
        $surveyQuestion = new SurveyQuestion;
        $this->fromRest($surveyQuestion);

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
        $this->authorize('show', SurveyQuestion::class);

        return $this->success($surveyQuestion);
    }

    /**
     * Update a survey question
     *
     * @param SurveyQuestion $surveyQuestion
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws ValidationException
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

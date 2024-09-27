<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Models\SurveyGroup;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class SurveyGroupController extends ApiController
{
    /**
     * Retrieve a survey group listing
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $params = request()->validate([
            'survey_id' => 'required|integer:exists:survey,id'
        ]);

        $survey = Survey::findOrFail($params['survey_id']);
        $this->authorize('index', [SurveyGroup::class, $survey]);
        return $this->success(SurveyGroup::findAllForSurvey($params['survey_id']), null, 'survey_group');
    }

    /**
     * Create a survey group
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function store(): JsonResponse
    {
        $surveyGroup = new SurveyGroup;
        $this->fromRest($surveyGroup);
        $this->authorize('store', $surveyGroup);

        if ($surveyGroup->save()) {
            return $this->success($surveyGroup);
        }

        return $this->restError($surveyGroup);
    }

    /**
     * Show a survey group
     *
     * SurveyGroup $surveyGroup
     * @param SurveyGroup $surveyGroup
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(SurveyGroup $surveyGroup): JsonResponse
    {
        $this->authorize('show', $surveyGroup);
        return $this->success($surveyGroup);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param SurveyGroup $surveyGroup
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function update(SurveyGroup $surveyGroup): JsonResponse
    {
        $this->authorize('update', $surveyGroup);
        $this->fromRest($surveyGroup);

        if ($surveyGroup->save()) {
            return $this->success($surveyGroup);
        }

        return $this->restError($surveyGroup);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param SurveyGroup $surveyGroup
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(SurveyGroup $surveyGroup): JsonResponse
    {
        $this->authorize('destroy', $surveyGroup);
        $surveyGroup->delete();

        return $this->restDeleteSuccess();
    }
}

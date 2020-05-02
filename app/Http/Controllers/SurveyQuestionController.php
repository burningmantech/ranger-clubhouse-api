<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

use Illuminate\Support\Facades\DB;

use App\Models\SurveyQuestion;

use App\Http\Controllers\ApiController;

class SurveyQuestionController extends ApiController
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
            'survey_id' => 'required|integer:exists:survey_group,id'
        ]);

        $this->authorize('index', SurveyQuestion::class);
        return $this->success(SurveyQuestion::findAllForSurvey($params['survey_id']), null, 'survey_question');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse
     */
    public function store()
    {
        $this->authorize('store', [SurveyQuestion::class]);
        $surveyQuestion = new SurveyQuestion;
        $this->fromRest($surveyQuestion);

        if ($surveyQuestion->save()) {
            return $this->success($surveyQuestion);
        }

        return $this->restError($surveyQuestion);
    }

    /**
     * Display the specified resource.
     *
     * SurveyQuestion $surveyQuestion
     * @return JsonResponse
     */

    public function show(SurveyQuestion $surveyQuestion)
    {
        $this->authorize('show', [SurveyQuestion::class]);

        return $this->success($surveyQuestion);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param SurveyQuestion $surveyQuestion
     * @return JsonResponse
     */
    public function update(SurveyQuestion $surveyQuestion)
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
     */
    public function destroy(SurveyQuestion $surveyQuestion)
    {
        $this->authorize('destroy', $surveyQuestion);
        $surveyQuestion->delete();
        DB::table('survey_answer')->where('survey_question_id', $surveyQuestion->id)->delete();

        return $this->restDeleteSuccess();
    }
}

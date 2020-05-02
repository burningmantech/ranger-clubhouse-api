<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

use Illuminate\Support\Facades\DB;

use App\Models\SurveyGroup;

use App\Http\Controllers\ApiController;

class SurveyGroupController extends ApiController
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
            'survey_id' => 'required|integer:exists:survey,id'
        ]);

        $this->authorize('index', SurveyGroup::class);
        return $this->success(SurveyGroup::findAllForSurvey($params['survey_id']), null, 'survey_group');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse
     */
    public function store()
    {
        $this->authorize('store', [SurveyGroup::class]);
        $surveyGroup = new SurveyGroup;
        $this->fromRest($surveyGroup);

        if ($surveyGroup->save()) {
            return $this->success($surveyGroup);
        }

        return $this->restError($surveyGroup);
    }

    /**
     * Display the specified resource.
     *
     * SurveyGroup $surveyGroup
     * @return JsonResponse
     */

    public function show(SurveyGroup $surveyGroup)
    {
        $this->authorize('show', [SurveyGroup::class]);

        return $this->success($surveyGroup);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param SurveyGroup $surveyGroup
     * @return JsonResponse
     */
    public function update(SurveyGroup $surveyGroup)
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
     */
    public function destroy(SurveyGroup $surveyGroup)
    {
        $this->authorize('destroy', $surveyGroup);
        $surveyGroup->delete();

        foreach ([ 'survey_question', 'survey_answer'] as $table) {
            DB::table($table)->where('survey_group_id', $surveyGroup->id)->delete();
        }
        return $this->restDeleteSuccess();
    }
}

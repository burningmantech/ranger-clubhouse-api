<?php

namespace App\Models;

use App\Models\ApiModel;

class SurveyQuestion extends ApiModel
{
    protected $table = 'survey_question';
    public $timestamps = true;

    protected $fillable = [
        'survey_id',
        'survey_group_id',
        'sort_index',
        'description',
        'is_required',
        'options',
        'type',
        'code'
    ];

    protected $rules = [
        'survey_id' => 'required|integer|exists:survey,id',
        'survey_group_id' => 'required|integer|exists:survey_group,id',
        'sort_index' => 'required|integer',
        'description' => 'required|string',
        'is_required' => 'sometimes|boolean',
        'code' => 'required|string',
        'type' => 'required|string',
    ];

    protected $casts = [
        'is_required' => 'boolean'
    ];

    protected $attributes = [
        'options' => ''
    ];

    const RATING = 'rating';
    const OPTIONS = 'options';
    const TEXT = 'text';

    public static function findAllForSurvey(int $surveyId)
    {
        return self::where('survey_id', $surveyId)
            ->orderBy('sort_index')
            ->get();
    }

    public static function findAllForSurveyGroup(int $surveyGroup)
    {
        return self::where('survey_group_id', $surveyGroup)
            ->orderBy('sort_index')
            ->get();
    }

    public function setOptionsAttribute($value)
    {
        $this->attributes['options'] = empty($value) ? '' : $value;
    }

    public function setCodeAttribute($value)
    {
        $this->attributes['code'] = empty($value) ? '' : $value;
    }
}


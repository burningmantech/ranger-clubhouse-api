<?php

namespace App\Models;


use App\Models\ApiModel;

class SurveyQuestion extends ApiModel
{
    protected $table = 'survey_question';
    protected $auditModel = true;
    public $timestamps = true;

    protected $fillable = [
        'survey_id',
        'survey_group_id',
        'sort_index',
        'description',
        'is_required',
        'options',
        'type',
        'summarize_rating'
     ];

    protected $rules = [
        'type' => 'required|string',
        'survey_id' => 'required|integer|exists:survey,id',
        'survey_group_id' => 'required|integer|exists:survey_group,id',
        'sort_index' => 'required|integer',
        'description' => 'required|string',
        'is_required' => 'sometimes|boolean',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'summarize_rating' => 'boolean'
    ];

    protected $attributes = [
        'options' => ''
    ];

    private $_optionLabels;

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

    public function responseToOptionLabel($value) {
        if (empty($this->options)) {
            return $value;
        }

        if (empty($this->_optionLabels)) {
            $this->_optionLabels = explode("\n", $this->options);
        }

        return $this->_optionLabels[$value - 1] ?? $value;
    }
}


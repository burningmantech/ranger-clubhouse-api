<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Models\SurveyQuestion;

class SurveyGroup extends ApiModel
{
    protected $table = 'survey_group';
    protected $auditModel = true;
    public $timestamps = true;

    protected $fillable = [
        'survey_id',
        'sort_index',
        'title',
        'description',
        'is_trainer_group'
    ];

    protected $rules = [
        'survey_id' => 'required|integer',
        'sort_index' => 'required|integer',
        'title' => 'required|string|max:190',
        'description' => 'sometimes|string',
        'is_trainer_group' => 'sometimes|boolean'
    ];

    protected $casts = [
        'is_trainer_group' => 'boolean'
    ];

    public function survey_questions() {
        return $this->hasMany(SurveyQuestion::class);
    }

    public static function findAllForSurvey(int $surveyId)
    {
        return self::where('survey_id', $surveyId)->orderBy('sort_index')->get();
    }

    public function setDescriptionAttribute($value) {
        $this->attributes['description'] = empty($value) ? '' : $value;
    }
}

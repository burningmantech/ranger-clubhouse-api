<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Models\SurveyQuestion;

class SurveyGroup extends ApiModel
{
    protected $table = 'survey_group';
    protected $auditModel = true;
    public $timestamps = true;

    // Group is nothing special, presented with the main report.
    const TYPE_NORMAL = 'normal';

    // The group questions are repeated for each trainer who taught
    const TYPE_TRAINER = 'trainer';
    // The group is separated out from the main report, and presented it's own thing.
    // with each slot
    const TYPE_SEPARATE = 'separate-slot';
    // The group is separated out from the main report and summarized
    // (e.g., the manual review)
    const TYPE_SUMMARY = 'separate-summary';

    protected $fillable = [
        'type',
        'survey_id',
        'sort_index',
        'title',
        'description',
        'report_title',
    ];

    protected $rules = [
        'survey_id' => 'required|integer',
        'sort_index' => 'required|integer',
        'title' => 'required|string|max:190',
        'description' => 'sometimes|string',
        'report_title' => 'sometimes|string',
    ];

    protected $attributes = [
        'sort_index' => 1,
        'report_title' => ''
    ];

    public function survey_questions()
    {
        return $this->hasMany(SurveyQuestion::class);
    }

    public static function findAllForSurvey(int $surveyId)
    {
        return self::where('survey_id', $surveyId)->orderBy('sort_index')->get();
    }

    public function setDescriptionAttribute($value)
    {
        $this->attributes['description'] = empty($value) ? '' : $value;
    }

    public function setReportTitleAttribute($value)
    {
        $this->attributes['report_title'] = empty($value) ? '' : $value;
    }

    public function setTypeAttribute($value)
    {
        $this->attributes['type'] = empty($value) ? '' : $value;
    }

    public function getReportId() {
        if ($this->type == self::TYPE_NORMAL) {
            return 'main';
        } else {
            return $this->id;
        }
    }

    public function getReportTitleDefault() {
        switch ($this->type) {
            case self::TYPE_NORMAL:
                return '';
            case self::TYPE_TRAINER:
                return 'Trainee-On-Trainer Feedback';
            default:
                return $this->report_title;
        }
    }
}

<?php

namespace App\Models;

use App\Attributes\BlankIfEmptyAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class SurveyGroup extends ApiModel
{
    protected $table = 'survey_group';
    protected bool $auditModel = true;
    public $timestamps = true;

    // Group is nothing special, presented with the main report.
    const string TYPE_NORMAL = 'normal';

    // The group questions are repeated for each trainer who taught
    const string TYPE_TRAINER = 'trainer';
    // The group is separated out from the main report, and presented it's own thing.
    // with each slot
    const string TYPE_SEPARATE = 'separate-slot';
    // The group is separated out from the main report and summarized
    // (e.g., the manual review)
    const string TYPE_SUMMARY = 'separate-summary';


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
        'description' => 'sometimes|string|nullable',
        'report_title' => 'sometimes|string|nullable',
    ];

    protected $attributes = [
        'sort_index' => 1,
        'report_title' => ''
    ];

    public static function boot() : void
    {
        parent::boot();
        self::deleted(function ($model) {
            DB::table('survey_question')->where('survey_group_id', $model->id)->delete();
            DB::table('survey_answer')->where('survey_group_id', $model->id)->delete();
        });
    }

    public function survey_questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class);
    }

    public function survey() : BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public static function findAllForSurvey(int $surveyId): Collection
    {
        return self::where('survey_id', $surveyId)->orderBy('sort_index')->get();
    }

    public function description(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function reportTitle(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function type(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function getReportId()
    {
        if ($this->type == self::TYPE_NORMAL) {
            return 'main';
        } else {
            return $this->id;
        }
    }

    public function getReportTitleDefault($surveyType)
    {
         switch ($this->type) {
            case self::TYPE_NORMAL:
                return '';
            case self::TYPE_TRAINER:
                if ($surveyType == Survey::ALPHA) {
                    return "Alpha Feedback For Mentors";
                }
                return Survey::TYPE_FOR_REPORTS_LABELS[$surveyType] ?? $this->type;
            default:
                return $this->report_title;
        }
    }
}

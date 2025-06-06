<?php

namespace App\Models;


use App\Attributes\BlankIfEmptyAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class SurveyQuestion extends ApiModel
{
    protected $table = 'survey_question';
    protected bool $auditModel = true;
    public $timestamps = true;

    const string TYPE_RATING = 'rating';
    const string TYPE_OPTIONS = 'options';
    const string TYPE_TEXT = 'text';

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

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'summarize_rating' => 'boolean',
        ];
    }

    protected $attributes = [
        'options' => ''
    ];

    private $_optionLabels;


    public static function boot(): void
    {
        parent::boot();
        self::deleted(function ($model) {
            DB::table('survey_answer')->where('survey_question_id', $model->id)->delete();
        });
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function survey_group(): BelongsTo
    {
        return $this->belongsTo(SurveyGroup::class);
    }

    public function survey_answers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class);
    }

    public static function findAllForSurvey(int $surveyId): Collection
    {
        return self::where('survey_id', $surveyId)
            ->with('survey_group:id,sort_index')
            ->get()
            ->sort(fn($a, $b) => $a->survey_group->sort_index - $b->survey_group->sort_index ?: $a->sort_index - $b->sort_index)
            ->values();
    }

    public static function findAllForSurveyGroup(int $surveyGroup): Collection
    {
        return self::where('survey_group_id', $surveyGroup)
            ->orderBy('sort_index')
            ->get();
    }

    public function options(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function responseToOptionLabel($value): ?string
    {
        if (empty($this->options)) {
            return $value;
        }

        if (empty($this->_optionLabels)) {
            $this->_optionLabels = explode("\n", $this->options);
        }

        return $this->_optionLabels[$value - 1] ?? $value;
    }
}


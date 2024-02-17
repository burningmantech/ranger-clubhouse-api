<?php

namespace App\Models;

use App\Attributes\NullIfEmptyAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class SurveyAnswer extends ApiModel
{
    protected $table = 'survey_answer';
    public $timestamps = true;

    /*
     * Note the survey_answer.callsign column has the callsign value from the survey form from 2019 and prior.
     * The field was free form, and not validated. The reporting code still uses the column if person_id is 0.
     */

    protected $fillable = [
        'can_share_name',
        'person_id',
        'response',
        'slot_id',
        'survey_group_id',
        'survey_id',
        'survey_question_id',
        'trainer_id',
    ];

    protected $rules = [
        'can_share_name' => 'sometimes|boolean',
        'person_id' => 'required|integer',
        'response' => 'required',
        'slot_id' => 'sometimes|integer|nullable',
        'survey_group_id' => 'required|integer',
        'survey_id' => 'required|integer',
        'survey_question_id' => 'required|integer',
        'trainer_id' => 'sometimes|integer|nullable',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'can_share_name' => false,
    ];

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Delete all answer from a person for a slot
     *
     * @param int $surveyId
     * @param int $personId
     * @param int $slotId
     */

    public static function deleteAllForPersonSlot(int $surveyId, int $personId, int $slotId): void
    {
        DB::table('survey_answer')
            ->where('survey_id', $surveyId)
            ->where('person_id', $personId)
            ->where('slot_id', $slotId)
            ->delete();
    }

    /**
     * Is a response required for an Alpha survey? (there's only one to answer per year, don't need to worry about
     * multiple surveys of the same time as the Trainer surveys where multiple sessions are possible)
     *
     * @param int $personId
     * @param int $year
     * @return bool
     */

    public static function needAlphaSurveyResponse(int $personId, int $year) : bool {
        $surveyId = DB::table('survey')->where(['year' => $year, 'type' => Survey::ALPHA])->value('id');
        if (!$surveyId) {
            return false;
        }

        return !DB::table('survey_answer')->where([ 'survey_id' => $surveyId, 'person_id' => $personId ])->exists();
    }

    /**
     * Delete all survey answer for a person
     *
     * @param int $surveyId
     * @param int $personId
     * @return void
     */

    public static function deleteAllForSurvey(int $surveyId, int $personId): void
    {
        DB::table('survey_answer')
            ->where('survey_id', $surveyId)
            ->where('person_id', $personId)
            ->delete();
    }

    /**
     * Delete all answers related to a particular slot.
     *
     * @param int $slotId
     */

    public static function deleteForSlot(int $slotId): void
    {
        self::where('slot_id', $slotId)->delete();
    }

    /**
     * Does the person have feedback as a trainer?
     * @param $personId
     * @return bool
     */

    public static function haveTrainerFeedback($personId): bool
    {
        return self::where('trainer_id', $personId)->exists();
    }

    public function trainerId(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function slotId(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }
}

<?php

namespace App\Models;

use App\Attributes\NullIfEmptyAttribute;
use App\Exceptions\UnacceptableConditionException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PersonLanguage extends ApiModel
{
    /*
     * language_name represents the common languages encountered on playa.
     * language_custom represents the lesser known languages.
     *
     * By splitting up the language classification, this helps to reduce the "noise" in the reports, and help
     * guide the user to a desired answer. In the past, people have entered "love", "snark", "dpw", "hippy" as
     * a language known. Not helpful!
     */

    protected $table = 'person_language';

    public $timestamps = false;
    public bool $auditModel = true;

    protected $guarded = [];

    const int OFF_DUTY = 1;
    const int ON_DUTY = 2;
    const int HAS_RADIO = 3;

    const string PROFICIENCY_UNKNOWN = 'unknown';
    const string PROFICIENCY_BASIC = 'basic';
    const string PROFICIENCY_INTERMEDIATE = 'intermediate';
    const string PROFICIENCY_FLUENT = 'fluent';

    const string LANGUAGE_NAME_CUSTOM = 'custom';

    /*
     * The common most known languages on playa. The list is used for options. If a
     * new language is added, or deleted, some database mucking about has to be done.
     */

    const array COMMON_PLAYA_LANGUAGES = [
        'American Sign Language',
        'Arabic',
        'Chinese (Mandarin)',
        'Cantonese',
        'Danish',
        'Dutch',
        'English',
        'Farsi',
        'French',
        'German',
        'Hebrew',
        'Hindi',
        'Italian',
        'Japanese',
        'Norwegian',
        'Polish',
        'Portuguese',
        'Punjabi',
        'Romanian',
        'Russian',
        'Spanish',
        'Swedish',
        'Tagalog',
        'Urdu',
    ];

    public $rules = [
        'language_name' => 'required|string|max:32',
        'language_custom' => 'required_if:language_name,custom|string|max:32|nullable',
        'proficiency' => 'required|string'
    ];

    public static function boot(): void
    {
        parent::boot();

        self::saving(function ($model) {
            if ($model->language_name !== self::LANGUAGE_NAME_CUSTOM && !empty($model->language_custom)) {
                $model->language_custom = null;
            }
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Retrieve records based on the given query
     *
     * @param array $query
     * @return Collection
     */

    public static function findForQuery(array $query): Collection
    {
        $personId = $query['person_id'] ?? null;

        $sql = self::query();
        if ($personId) {
            $sql->where('person_id', $personId);
        }

        $rows = $sql->get();
        return $rows->sort(function ($a, $b) {
            if ($a->person_id == $b->person_id) {
                return strcasecmp($a->actualName(), $b->actualName());
            } else {
                return $a->person_id - $b->person_id;
            }
        })->values();
    }

    /**
     * Find the speakers of the given language.
     *
     * @param string $language
     * @param bool $includeOffSite
     * @param int $status
     * @return Collection
     * @throws UnacceptableConditionException
     */

    public static function findSpeakers(string $language, bool $includeOffSite, int $status): Collection
    {
        $sql = self::select('language_name', 'language_custom', 'proficiency', 'person.id as person_id', 'callsign')
            ->join('person', 'person.id', '=', 'person_language.person_id')
            ->whereAny(['language_name', 'language_custom'], 'like', '%' . $language . '%')
            ->whereIn('person.status', Person::ACTIVE_STATUSES)
            ->orderBy('callsign');

        if (!$includeOffSite && $status != PersonLanguage::ON_DUTY) {
            $sql->where('person.on_site', 1);
        }

        $year = current_year();

        switch ($status) {
            case PersonLanguage::OFF_DUTY:
                $sql->whereNotExists(function ($q) use ($year) {
                    $q->from('timesheet')
                        ->select(DB::raw(1))
                        ->whereColumn('person.id', 'timesheet.person_id')
                        ->whereYear('on_duty', $year)
                        ->whereNull('timesheet.off_duty')
                        ->limit(1);
                });
                break;

            case PersonLanguage::ON_DUTY:
                $sql->join('timesheet', 'person.id', 'timesheet.person_id')
                    ->join('position', 'position.id', 'timesheet.position_id')
                    ->whereYear('timesheet.on_duty', $year)
                    ->whereNull('timesheet.off_duty')
                    ->addSelect('position.title as position_title');
                break;

            case PersonLanguage::HAS_RADIO:
                $sql->whereExists(function ($q) use ($year) {
                    $q->from('asset_person')
                        ->select(DB::raw(1))
                        ->join('asset', 'asset_person.asset_id', 'asset.id')
                        ->where('asset.description', Asset::TYPE_RADIO)
                        ->whereColumn('asset_person.person_id', 'person_language.person_id')
                        ->whereYear('asset_person.checked_out', $year)
                        ->whereNull('asset_person.checked_in')
                        ->limit(1);
                });
                break;

            default:
                throw new UnacceptableConditionException("Unknown status [$status]");
        }

        return $sql->get();
    }

    public function languageCustom(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function actualName(): string
    {
        return $this->language_name == self::LANGUAGE_NAME_CUSTOM ? $this->language_custom : $this->language_name;
    }
}

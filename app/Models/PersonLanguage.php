<?php

namespace App\Models;

use Exception;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PersonLanguage extends ApiModel
{
    const OFF_DUTY = 1;
    const ON_DUTY = 2;
    const HAS_RADIO = 3;

    const LANGUAGE_NAME_LENGTH = 32;

    protected $table = 'person_language';

    /*
     * All fields are mass-assignable
      * @var string
      */
    protected $guarded = [];

    /**
     * Don't use created_at/updated_at.
     * @var bool
     */
    public $timestamps = false;

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    /*
     * Retrieve a comma-separated list of language spoken by a person
     * @var integer $person_id Person to lookup
     * @return string a command list of languages
     */

    public static function retrieveForPerson($person_id): string
    {
        $languages = self::where('person_id', $person_id)->pluck('language_name')->toArray();

        return join(', ', $languages);
    }

    /**
     *  Update the languages spoken by a person
     * @param $person_id
     * @param $language
     * @throws Exception
     */
    public static function updateForPerson($person_id, $language)
    {
        self::where('person_id', $person_id)->delete();

        $languages = preg_split('/([,\.;&]|\band\b)/', $language);

        foreach ($languages as $name) {
            $tongue = trim($name);

            if (empty($tongue)) {
                continue;
            }

            $tongue = substr($tongue, 0, self::LANGUAGE_NAME_LENGTH);
            self::create(['person_id' => $person_id, 'language_name' => $tongue]);
        }
    }

    public static function findSpeakers($language, $includeOffSite, $status)
    {
        $sql = self::select('language_name', 'person.id as person_id', 'callsign')
            ->where('language_name', 'like', '%' . $language . '%')
            ->join('person', 'person.id', '=', 'person_language.person_id')
            ->whereIn('person.status', Person::ACTIVE_STATUSES)
            ->orderBy('callsign');

        if (!$includeOffSite) {
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
                $sql->join('timesheet', 'person.id', '=', 'timesheet.person_id')
                    ->whereYear('timesheet.on_duty', $year)
                    ->whereNull('timesheet.off_duty');
                break;

            case PersonLanguage::HAS_RADIO:
                $sql->whereExists(function ($q) use ($year) {
                    $q->from('asset_person')
                        ->select(DB::raw(1))
                        ->join('asset', 'asset_person.asset_id', 'asset.id')
                        ->where('asset.description', 'Radio')
                        ->whereColumn('asset_person.person_id', 'person_language.person_id')
                        ->whereYear('asset_person.checked_out', $year)
                        ->whereNull('asset_person.checked_in')
                        ->limit(1);
                });
                break;

            default:
                throw new InvalidArgumentException("Unknown status [$status]");
        }

        return $sql->get();
    }
}

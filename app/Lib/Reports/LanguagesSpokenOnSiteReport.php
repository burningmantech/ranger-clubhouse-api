<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\PersonLanguage;

class  LanguagesSpokenOnSiteReport
{
    /**
     * Report on the languages spoken by  active AND on site folks.
     */

    public static function execute(): array
    {
        $personId = Person::where('on_site', true)
            ->whereIn('status', Person::ACTIVE_STATUSES)
            ->pluck('id');

        $common = PersonLanguage::whereIntegerInRaw('person_id', $personId)
            ->with(['person:id,callsign'])
            ->where('language_name', '!=', PersonLanguage::LANGUAGE_NAME_CUSTOM)
            ->orderBy('language_name')
            ->get()
            ->filter(fn($row) => $row->person != null)
            ->groupBy(fn($r) => strtolower(trim($r->language_name)));

        $other = PersonLanguage::whereIntegerInRaw('person_id', $personId)
            ->with(['person:id,callsign'])
            ->where('language_name', PersonLanguage::LANGUAGE_NAME_CUSTOM)
            ->orderBy('language_custom')
            ->get()
            ->filter(fn($row) => $row->person != null)
            ->groupBy(fn($r) => strtolower(trim($r->language_custom)));

        return [
            'common' => self::buildLanguages($common, true),
            'other' => self::buildLanguages($other, false)
        ];
    }

    public static function buildLanguages($languages, $isCommon)
    {
        return $languages->map(function ($people)  use ($isCommon) {
            return [
                'name' => $isCommon ? $people[0]->language_name : $people[0]->language_custom,
                'people' => $people->map(function ($row) {
                    return [
                        'id' => $row->person_id,
                        'callsign' => $row->person->callsign
                    ];
                })->sortBy('callsign', SORT_NATURAL | SORT_FLAG_CASE)->values()
            ];
        })->sortBy('language', SORT_NATURAL | SORT_FLAG_CASE)->values();

    }

}
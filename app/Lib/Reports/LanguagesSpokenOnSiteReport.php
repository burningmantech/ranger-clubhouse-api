<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\PersonLanguage;

class  LanguagesSpokenOnSiteReport
{
    /**
     * Report on the languages spoken by  active AND on site folks.
     */

    public static function execute()
    {
        $personId = Person::where('on_site', true)
            ->whereIn('status', Person::ACTIVE_STATUSES)
            ->pluck('id');

        $languages = PersonLanguage::whereIntegerInRaw('person_id', $personId)
            ->with(['person:id,callsign'])
            ->orderBy('language_name')
            ->get()
            ->filter(fn($row) => $row->person != null)
            ->groupBy(fn($r) => strtolower(trim($r->language_name)));

        $rows = $languages->map(function ($people, $name) {
            return [
                'language' => $people[0]->language_name,
                'people' => $people->map(function ($row) {
                    return [
                        'id' => $row->person_id,
                        'callsign' => $row->person->callsign
                    ];
                })->sortBy('callsign', SORT_NATURAL | SORT_FLAG_CASE)->values()
            ];
        })->sortBy('language', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return $rows;
    }

}
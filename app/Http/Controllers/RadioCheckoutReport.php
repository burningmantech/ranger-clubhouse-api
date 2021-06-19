<?php

namespace App\Http\Controllers;

use App\Models\AssetPerson;
use Illuminate\Support\Facades\DB;

class RadioCheckoutReport
{
    /**
     * Find all radios that were checked out with duration.
     *
     * query types are:
     * year: the year to find radios, defaults to current year if not present
     * include_qualified: include people who qualified for event radios, otherwise find only shift qualified individuals
     * event_summary: report on all radios still checked out or was checked out over the hour limit.
     * hour_limit: rpeort on radios checked out for more than X hours. defaults to 14
     *
     * @param $query
     * @return AssetPerson[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection
     */

    public static function execute($query)
    {
        $year = $query['year'] ?? current_year();
        $now = (string) now();
        $sql = AssetPerson::select(
            'asset_person.person_id',
            'person.callsign',
            'asset_person.checked_out',
            'asset_person.checked_in',
            'asset.barcode',
            'asset.perm_assign',
            DB::raw("(UNIX_TIMESTAMP(IFNULL(asset_person.checked_in, '$now')) - UNIX_TIMESTAMP(asset_person.checked_out)) AS duration"),
            DB::raw('IF(radio_eligible.max_radios > 0, true,false) AS eligible')
        )
            ->join('person', 'person.id', 'asset_person.person_id')
            ->join('asset', 'asset.id', 'asset_person.asset_id')
            ->whereYear('checked_out', $year)
            ->where('description', 'radio');

        $sql->leftJoin('radio_eligible', function ($query) use ($year) {
            $query->whereRaw('radio_eligible.person_id=asset_person.person_id');
            $query->where('year', $year);
        });

        $seconds = ($query['hour_limit'] ?? 14) * 3600;

        if (isset($query['event_summary'])) {
            $sql->where(function ($q) use ($seconds) {
                $q->whereNull('checked_in');
                $q->orWhereRaw("(UNIX_TIMESTAMP(asset_person.checked_in) - UNIX_TIMESTAMP(asset_person.checked_out)) > $seconds");
            });
        } else {
            $sql->whereRaw("(? - UNIX_TIMESTAMP(asset_person.checked_out)) > $seconds", [ now()->timestamp ])->whereNull('checked_in');
        }

        if (empty($query['include_qualified'])) {
            $sql->whereNull('radio_eligible.max_radios');
        }

        return $sql->orderBy('person.callsign')->orderBy('asset_person.checked_out')->get();
    }


}
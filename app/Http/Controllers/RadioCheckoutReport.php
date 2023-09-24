<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetPerson;
use App\Models\Provision;
use Illuminate\Support\Facades\DB;

class RadioCheckoutReport
{
    /**
     * Find all radios that were checked out with duration.
     *
     * query types are:
     * year: the year to find radios, defaults to current year if not present
     * include_qualified: include people who qualified for event radios, otherwise find only shift qualified individuals'
     * event_summary: report on all radios still checked out or was checked out over the hour limit.
     * hour_limit: report on radios checked out for more than X hours. defaults to 14
     *
     * @param $query
     * @return array
     */

    public static function execute($query): array
    {
        $now = (string)now();

        $year = $query['year'] ?? current_year();
        $seconds = ($query['hour_limit'] ?? 14) * 3600;
        $eventSummary = $query['event_summary'] ?? null;
        $includeQualified = $query['include_qualified'] ?? null;

        $sql = AssetPerson::select(
            'asset_person.person_id',
            'person.callsign',
            'asset_person.checked_out',
            'asset_person.check_out_person_id',
            'asset_person.checked_in',
            'asset_person.check_in_person_id',
            'asset.barcode',
            'asset.perm_assign',
            DB::raw("(UNIX_TIMESTAMP(IFNULL(asset_person.checked_in, '$now')) - UNIX_TIMESTAMP(asset_person.checked_out)) AS duration"),
        )
            ->join('person', 'person.id', 'asset_person.person_id')
            ->join('asset', 'asset.id', 'asset_person.asset_id')
            ->whereYear('checked_out', $year)
            ->where('type', Asset::TYPE_RADIO);

        if ($eventSummary) {
            $sql->where(function ($q) use ($seconds) {
                $q->whereNull('checked_in');
                $q->orWhereRaw("(UNIX_TIMESTAMP(asset_person.checked_in) - UNIX_TIMESTAMP(asset_person.checked_out)) > $seconds");
            });
        } else {
            $sql->whereRaw("(? - UNIX_TIMESTAMP(asset_person.checked_out)) > $seconds", [now()->timestamp])->whereNull('checked_in');
        }

        $sql->with(['check_out_person:id,callsign', 'check_in_person:id,callsign']);

        $rows = $sql->orderBy('person.callsign')->orderBy('asset_person.checked_out')->get();

        if ($rows->isNotEmpty()) {
            /*
             * See if the person is eligible for an event radio.
             *
             * Check to see if an event radio provision was used in the selected year
             * If the year is current, also look up available, claimed, and submitted provisions.
             * (don't include active provisions when determining eligibly in past years.)
             */

            $provisions = Provision::whereIn('person_id', $rows->pluck('person_id'))
                ->where('type', Provision::EVENT_RADIO)
                ->where(function ($q) use ($year) {
                    if ($year == current_year()) {
                        $q->where(function ($p) {
                            $p->whereIn('status', [Provision::AVAILABLE, Provision::CLAIMED, Provision::SUBMITTED]);
                        });
                    }
                    $q->orWhere(function ($p) use ($year) {
                        $p->where('status', Provision::USED);
                        $p->where('consumed_year', $year);
                    });
                })->get()
                ->groupBy('person_id');
        } else {
            $provisions = null;
        }

        $results = [];
        foreach ($rows as $row) {
            $eligible = $provisions && $provisions->has($row->person_id);
            if (!$includeQualified && !$eligible) {
                continue;
            }

            $results[] = [
                'person_id' => $row->person_id,
                'callsign' => $row->callsign,
                'checked_out' => (string)$row->checked_out,
                'check_out_person' => $row->check_out_person,
                'checked_in' => $row->checked_in ? (string)$row->checked_in : null,
                'check_in_person' => $row->check_in_person,
                'barcode' => $row->barcode,
                'duration' => $row->duration,
                'eligible' => $eligible,
                'perm_assign' => $row->perm_assign,
            ];
        }

        return $results;
    }
}
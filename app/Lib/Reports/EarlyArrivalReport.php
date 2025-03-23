<?php

namespace App\Lib\Reports;

use App\Models\AccessDocument;
use App\Models\Asset;
use App\Models\AssetPerson;
use App\Models\Bmid;
use App\Models\EventDate;
use App\Models\Provision;
use Carbon\Carbon;
use Illuminate\Support\Arr;

/**
 * Report on people who arrive prior to Thursday before gate opening weekend.
 * Used to build arrival packets for SITE Setup individuals.
 */

class EarlyArrivalReport
{
    public static function execute(int $year): array
    {
        $eventDate = EventDate::findForYear($year);
        if (!$eventDate) {
            return ['status' => 'no-event-dates'];
        }

        $priorTo = Carbon::parse($eventDate->event_start->format('Y-m-d') . ' last Thursday');
        $priorToFormatted = $priorTo->format('Y-m-d');
        $saps = AccessDocument::retrieveSAPsPriorTo($priorTo);

        if (empty($saps)) {
            return [
                'status' => 'no-arrivals',
                'arrivals' => [],
                'date' => $priorToFormatted,
            ];
        }


        $arrivals = [];
        $personIds = Arr::pluck($saps, 'person_id');
        $bmids = Bmid::findForPersonIds($year, $personIds)->keyBy('person_id');
        $radiosForPeople = Provision::retrieveTypeForPersonIds(Provision::EVENT_RADIO, $personIds);
        $checkedOutRadios = AssetPerson::retrieveTypeForPersonIds(Asset::TYPE_RADIO, $personIds);
        foreach ($saps as $sap) {
            $allowedRadios = $radiosForPeople->get($sap->person_id);
            $radioCount = 0;
            if ($allowedRadios) {
                foreach ($allowedRadios as $radio) {
                    $radioCount += $radio->item_count;
                }
            }

            $radios = $checkedOutRadios->get($sap->person_id);
            $issuedRadios = [];
            if ($radios) {
                foreach ($radios as $radio) {
                    $issuedRadios[] = [
                        'barcode' => $radio->asset->barcode,
                        'is_event_radio' => $radio->asset->perm_assigned,
                        'checked_out' => $radio->checked_out,
                    ];
                }

                usort($issuedRadios, fn($a, $b) => strcmp($a['barcode'], $b['barcode']));
            }

            $person = $sap->person;
            $arrivals[] = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'status' => $person->status,
                'on_site' => $person->on_site,
                'radios_allowed' => $radioCount,
                'radios' => $issuedRadios,
                'sap_date' => $sap->access_date->format('Y-m-d'),
                'bmid_status' => $bmids->get($person->id)?->status ?? 'no-bmid',
            ];
        }

        usort($arrivals, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));
        return [
            'status' => 'success',
            'arrivals' => $arrivals,
            'date' => $priorToFormatted,
        ];
    }
}
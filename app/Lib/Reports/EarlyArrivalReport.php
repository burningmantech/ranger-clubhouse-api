<?php

namespace App\Lib\Reports;

use App\Models\AccessDocument;
use App\Models\Asset;
use App\Models\AssetPerson;
use App\Models\Bmid;
use App\Models\EventDate;
use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\PersonSlot;
use App\Models\Position;
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

        $preEventEnd = Carbon::parse($eventDate->event_start->format('Y-m-d') . ' last Thursday');
        $preEventEndFormatted = $preEventEnd->format('Y-m-d');
        $saps = AccessDocument::retrieveSAPsPriorTo($preEventEnd);
        $preEventStart = $preEventEnd->clone()->subDays(5);

        $workingShifts = PersonSlot::select('person_slot.*')
            ->join('slot', 'slot.id', '=', 'person_slot.slot_id')
            ->join('position', 'position.id', '=', 'slot.position_id')
            ->where('slot.begins_year', $year)
            ->where('position.type', '!=', Position::TYPE_TRAINING)
            ->whereBetween('slot.begins', [$preEventStart, $preEventEnd])
            ->with('slot', 'slot.position')
            ->orderBy('slot.begins')
            ->get();
        $workingPeople = $workingShifts->pluck('person_id')->unique();

        if (empty($saps) && $workingPeople->isEmpty()) {
            return [
                'status' => 'no-arrivals',
                'arrivals' => [],
                'pre_event_start' => $preEventStart->format('Y-m-d'),
                'pre_event_end' => $preEventEndFormatted,
            ];
        }

        $workingShifts = $workingShifts->groupBy('person_id');

        $arrivals = [];
        $personIds = Arr::pluck($saps, 'person_id');
        $personIds = $workingPeople->merge($personIds)->unique()->toArray();
        $bmids = Bmid::findForPersonIds($year, $personIds)->keyBy('person_id');
        $radiosForPeople = Provision::retrieveTypeForPersonIds(Provision::EVENT_RADIO, $personIds);
        $checkedOutRadios = AssetPerson::retrieveTypeForPersonIds(Asset::TYPE_RADIO, $personIds);
        $sapsById = collect($saps)->keyBy('person_id');
        $peopleEvent = PersonEvent::where('year', $year)->whereIn('person_id', $personIds)->get()->keyBy('person_id');

        $people = Person::whereIn('id', $personIds)->get()->keyBy('id');

        foreach ($personIds as $personId) {
            $sap = $sapsById->get($personId);
            $allowedRadios = $radiosForPeople->get($personId);
            $radioCount = 0;
            if ($allowedRadios) {
                foreach ($allowedRadios as $radio) {
                    if ($radio->item_count > $radioCount) {
                        $radioCount = $radio->item_count;
                    }
                }
            }

            $radios = $checkedOutRadios->get($personId);
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

            $person = $people->get($personId);
            $shifts = $workingShifts->get($personId);

            $firstShift = null;
            if ($shifts) {
                $signup = $shifts->first();
                $slot = $signup->slot;
                $firstShift = [
                    'begins' => (string) $slot->begins,
                    'description' => $slot->description,
                    'position_title' => $slot->position->title,
                ];
            }

            $arrivals[] = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'status' => $person->status,
                'on_site' => $person->on_site,
                'radio_agreement' => $peopleEvent->get($personId)?->asset_authorized ?? false,
                'radios_allowed' => $radioCount,
                'radios' => $issuedRadios,
                'sap_date' => ($sap ? ($sap->access_any_time ? 'any' : $sap->access_date->format('Y-m-d')) : 'no-sap'),
                'bmid_status' => $bmids->get($person->id)?->status ?? 'no-bmid',
                'first_shift' => $firstShift,
            ];
        }

        usort($arrivals, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));
        return [
            'status' => 'success',
            'arrivals' => $arrivals,
            'pre_event_start' => $preEventStart->format('Y-m-d'),
            'pre_event_end' => $preEventEndFormatted,
        ];
    }
}
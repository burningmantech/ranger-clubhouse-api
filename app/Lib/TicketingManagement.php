<?php

namespace App\Lib;

use App\Models\AccessDocument;
use App\Models\Person;
use Illuminate\Support\Facades\DB;


class TicketingManagement
{
    /**
     * Retrieve all access documents, group by people, that are claimed, qualified, or banked
     *
     * Return an array. Each element is an associative array:
     *
     * person info: id,callsign,status,first_name,last_name,email
     *     if $includeDelivery is true include - street1,city,state,zip,country
     * documents: array of access documents
     *
     * @param $forDelivery
     * @return array
     */

    public static function retrieveCurrentByPerson($forDelivery): array
    {
        $currentYear = current_year();

        if ($forDelivery) {
            $sql = AccessDocument::where('status', AccessDocument::CLAIMED);
        } else {
            $sql = AccessDocument::whereIn('status', AccessDocument::ACTIVE_STATUSES);
        }

        $rows = $sql->orderBy('source_year')->get();

        if ($rows->isNotEmpty()) {
            $personIds = array_unique($rows->pluck('person_id')->toArray());
            $ticketHolders = DB::table('person')
                ->select(
                    'id',
                    'callsign',
                    'status',
                    'first_name',
                    'last_name',
                    'email',
                    'home_phone',
                )
                ->whereIntegerInRaw('id', $personIds)
                ->orderBy('callsign')
                ->get();

            $peopleHaveStaffCredentials = DB::table('access_document')
                ->select('person_id')
                ->whereIntegerInRaw('person_id', $personIds)
                ->where('type', AccessDocument::STAFF_CREDENTIAL)
                ->whereIn('status', [AccessDocument::CLAIMED, AccessDocument::SUBMITTED])
                ->get()
                ->keyBy('person_id');
        } else {
            $peopleHaveStaffCredentials = collect([]);
            $ticketHolders = [];
        }

        $rows = $rows->groupBy('person_id');

        $people = [];

        $dateRange = setting('TAS_SAPDateRange');
        if ($dateRange) {
            list($low, $high) = explode("-", trim($dateRange));
        } else {
            $low = 5;
            $high = 26;
        }

        foreach ($ticketHolders as $holder) {
            $personId = $holder->id;
            $tickets = $rows->get($personId);
            if (!$tickets) {
                continue;
            }

            $people[] = [
                'person' => $holder,
                'documents' => $tickets
            ];

            foreach ($tickets as $row) {
                $errors = [];
                if ($holder->status == Person::DECEASED || $holder->status == Person::DISMISSED) {
                    continue;
                }
                switch ($row->type) {
                    case AccessDocument::STAFF_CREDENTIAL:
                    case AccessDocument::WAP:
                    case AccessDocument::WAPSO:
                        if (!$row->access_any_time) {
                            $accessDate = $row->access_date;
                            if (!$accessDate) {
                                $errors[] = "missing access date";
                            } elseif ($accessDate->year < $currentYear) {
                                $errors[] = "access date [$accessDate] is less than current year [$currentYear]";
                            } else {
                                $day = $accessDate->day;
                                if ($day < $low || $day > $high) {
                                    $errors[] = "access date [$accessDate] outside day [$day] range low [$low], high [$high]";
                                }
                            }
                        }
                        break;
                }

                $deliveryType = $row->delivery_method;
                if ($forDelivery) {
                    // Override delivery methods if need be.
                    switch ($row->type) {
                        case AccessDocument::STAFF_CREDENTIAL:
                            $deliveryType = AccessDocument::DELIVERY_WILL_CALL;
                            break;

                        case AccessDocument::WAP:
                        case AccessDocument::WAPSO:
                            $deliveryType = AccessDocument::DELIVERY_EMAIL;
                            break;

                        case AccessDocument::SPT:
                            if ($deliveryType == AccessDocument::DELIVERY_NONE) {
                                $errors[] = 'missing delivery method';
                            }
                            break;

                        case AccessDocument::VEHICLE_PASS_GIFT:
                        case AccessDocument::VEHICLE_PASS_SP:
                            $hasSC = $peopleHaveStaffCredentials->has($row->person_id);
                            if ($hasSC) {
                                $deliveryType = AccessDocument::DELIVERY_WILL_CALL;
                            } else if ($deliveryType == AccessDocument::DELIVERY_NONE) {
                                $errors[] = 'missing delivery method';
                            } else if ($deliveryType != AccessDocument::DELIVERY_POSTAL
                                && $deliveryType != AccessDocument::DELIVERY_PRIORITY) {
                                $deliveryType = AccessDocument::DELIVERY_WILL_CALL;
                            }
                            break;
                    }

                    $row->delivery_type = $deliveryType;
                    $holder->delivery_type = $deliveryType;
                }

                if (!empty($errors)) {
                    $row->error = implode('; ', $errors);
                    $row->has_error = true;
                }
            }
        }


        return [
            'people' => $people,
            'day_high' => (int)$high,
            'day_low' => (int)$low,
        ];
    }

    /**
     * Retrieve all people with expiring tickets for a given year.
     *
     * @param int $year
     * @return array
     */

    public static function retrieveExpiringTicketsByPerson(int $year): array
    {
        $rows = AccessDocument::whereIn('type', AccessDocument::REGULAR_TICKET_TYPES)
            ->whereIn('status', [AccessDocument::QUALIFIED, AccessDocument::BANKED])
            ->whereYear('expiry_date', '<=', $year)
            ->with(['person:id,callsign,email,status'])
            ->orderBy('source_year')
            ->get();

        $peopleByIds = [];
        foreach ($rows as $row) {
            if (!$row->person) {
                continue;
            }

            $personId = $row->person->id;
            if (!isset($peopleByIds[$personId])) {
                $peopleByIds[$personId] = [
                    'person' => $row->person,
                    'tickets' => []
                ];
            }

            $peopleByIds[$personId]['tickets'][] = [
                'id' => $row->id,
                'type' => $row->type,
                'status' => $row->status,
                'expiry_date' => (string)$row->expiry_date,
            ];
        }

        $people = array_values($peopleByIds);
        usort($people, fn($a, $b) => strcasecmp($a['person']->callsign, $b['person']->callsign));

        return $people;
    }
}
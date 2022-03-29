<?php

namespace App\Lib;

use App\Models\AccessDocument;
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

        $sql->whereNotIn('type', AccessDocument::PROVISION_TYPES);

        $rows = $sql->select(
            '*',
            DB::raw('EXISTS (SELECT 1 FROM access_document sc WHERE sc.person_id=access_document.person_id AND sc.type="staff_credential" AND sc.status IN ("claimed", "submitted") LIMIT 1) as has_staff_credential')
        )
            ->with(['person:id,callsign,status,first_name,last_name,email,home_phone,street1,street2,city,state,zip,country'])
            ->orderBy('source_year')
            ->get();

        $people = [];


        $dateRange = setting('TAS_WAPDateRange');
        if ($dateRange) {
            list($low, $high) = explode("-", trim($dateRange));
        } else {
            $low = 5;
            $high = 26;
        }

        foreach ($rows as $row) {
            // skip deleted person records
            if (!$row->person) {
                continue;
            }

            $personId = $row->person_id;

            if (!isset($people[$personId])) {
                $people[$personId] = (object)[
                    'person' => $row->person,
                    'documents' => []
                ];
            }

            $person = $people[$personId];
            $person->documents[] = $row;

            $errors = [];
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

            if ($forDelivery) {
                $deliveryMethod = $row->delivery_method;

                switch ($row->type) {
                    case AccessDocument::STAFF_CREDENTIAL:
                        $deliveryType = AccessDocument::DELIVERY_WILL_CALL;
                        break;

                    case AccessDocument::WAP:
                    case AccessDocument::WAPSO:
                        $deliveryType = AccessDocument::DELIVERY_EMAIL;
                        break;

                    case AccessDocument::RPT:
                        $deliveryType = $deliveryMethod;
                        if ($deliveryMethod == AccessDocument::DELIVERY_NONE) {
                            $errors[] = 'delivery method missing';
                        }
                        break;

                    case AccessDocument::VEHICLE_PASS:
                    case AccessDocument::GIFT:
                        if ($row->type == AccessDocument::VEHICLE_PASS && $row->has_staff_credential) {
                            $deliveryType = AccessDocument::DELIVERY_WILL_CALL;
                        } else if ($deliveryMethod == AccessDocument::DELIVERY_NONE) {
                            $errors[] = 'missing delivery method';
                        } else if ($deliveryMethod == AccessDocument::DELIVERY_POSTAL) {
                            if ($row->hasAddress()) {
                                $row->delivery_address = [
                                    'street' => $row->street,
                                    'city' => $row->city,
                                    'state' => $row->state,
                                    'postal_code' => $row->postal_code,
                                    'country' => 'US',
                                    'phone' => $row->person->home_phone,
                                ];
                            } else {
                                $errors[] = 'missing mailing address';
                            }
                            $deliveryType = AccessDocument::DELIVERY_POSTAL;
                        } else {
                            $deliveryType = AccessDocument::DELIVERY_WILL_CALL;
                        }
                        break;
                }

                $row->delivery_type = $deliveryType;
                $person->delivery_type = $deliveryType;
            }

            if (!empty($errors)) {
                $row->error = implode('; ', $errors);
                $row->has_error = true;
            }
        }

        usort($people, fn($a, $b) => strcasecmp($a->person->callsign, $b->person->callsign));

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
        $rows = AccessDocument::whereIn('type', AccessDocument::TICKET_TYPES)
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
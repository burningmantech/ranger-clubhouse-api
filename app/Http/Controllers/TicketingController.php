<?php

/*
 * TicketingController - a higher level controller which deals directly
 * with a person claiming, and updating details for their ticket package.
 *
 * See AccessDocumentController which handles the CRUD side of things.
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;

use App\Models\AccessDocument;
use App\Models\AccessDocumentChanges;
use App\Models\AccessDocumentDelivery;
use App\Models\Person;
use App\Models\Timesheet;

class TicketingController extends ApiController
{
    /*
     * Retrieve the ticketing information
     */

    public function ticketingInfo()
    {
        $settings = setting([
                'TicketingPeriod', 'TicketsAndStuffEnablePNV',
                'TAS_Tickets', 'TAS_VP', 'TAS_WAP', 'TAS_WAPSO', 'TAS_Delivery', 'TAS_WAPSOMax',
                'TAS_BoxOfficeOpenDate', 'TAS_DefaultWAPDate', 'TAS_WAPDateRange', 'TAS_DefaultAlphaWAPDate',
                'TAS_DefaultSOWAPDate', 'TAS_SubmitDate',
                'TicketVendorEmail', 'TicketVendorName', 'TAS_Email',
                'RpTicketThreshold', 'ScTicketThreshold', 'YrTicketThreshold', 'YrTicketThreshold',
                'TAS_Ticket_FAQ', 'TAS_WAP_FAQ', 'TAS_VP_FAQ', 'TAS_Alpha_FAQ',
                'TAS_Pickup_Locations'
            ]);

        return response()->json([
            'ticketing_info' => [
                'period'                 => $settings['TicketingPeriod'],
                'is_enabled_for_pnv'     => $settings['TicketsAndStuffEnablePNV'],

                'ticket_status'          => $settings['TAS_Tickets'],
                'vp_status'              => $settings['TAS_VP'],
                'wap_status'             => $settings['TAS_WAP'],
                'wap_so_status'          => $settings['TAS_WAPSO'],
                'delivery_status'        => $settings['TAS_Delivery'],
                'wap_so_max'             => $settings['TAS_WAPSOMax'],
                'box_office_open_date'   => $settings['TAS_BoxOfficeOpenDate'],
                'wap_default_date'       => $settings['TAS_DefaultWAPDate'],
                'wap_date_range'         => $settings['TAS_WAPDateRange'],
                'wap_alpha_default_date' => $settings['TAS_DefaultAlphaWAPDate'],
                'wap_so_default_date'    => $settings['TAS_DefaultSOWAPDate'],

                'submit_date'            => $settings['TAS_SubmitDate'],

                'ticket_vendor_email'    => $settings['TicketVendorEmail'],
                'ticket_vendor_name'     => $settings['TicketVendorName'],
                'ranger_ticketing_email' => $settings['TAS_Email'],

                'rpt_credits'            => $settings['RpTicketThreshold'],
                'sc_credits'             => $settings['ScTicketThreshold'],
                'earned_year'            => $settings['YrTicketThreshold'] - 1,
                'upcoming_year'          => $settings['YrTicketThreshold'],

                'pickup_locations'       => $settings['TAS_Pickup_Locations'],

                'faqs'                   => [
                    'ticketing'          => $settings['TAS_Ticket_FAQ'],
                    'wap'                => $settings['TAS_WAP_FAQ'],
                    'vp'                 => $settings['TAS_VP_FAQ'],
                    'alpha'              => $settings['TAS_Alpha_FAQ'],
                ]
            ]
        ]);
    }

    /*
     * Retrieve the ticketing package
     */

    public function package(Person $person)
    {
        $period  = setting('TicketingPeriod');

        $this->authorize('index', [ AccessDocument::class, $person->id ]);

        $rows = AccessDocument::findForQuery([ 'person_id' => $person->id ]);

        // Filter for the tickets
        $filtered = $rows->filter(function ($row) {
            return in_array($row->type, AccessDocument::TICKET_TYPES);
        });

        $tickets = [];
        $chosen = null;
        foreach ($filtered as $row) {
            $ticket = (object) [
                'id'          => $row->id,
                'type'        => $row->type,
                'status'      => $row->status,
                'source_year' => $row->source_year,
                'expiry_date' => (string)$row->expiry_date,
                'access_any_time' => $row->access_any_time,
                'access_date' => (string)$row->access_date,
            ];

            $tickets[] = $ticket;

            if ($row->status == 'claimed') {
                $chosen = $ticket;
            } elseif ($chosen == null ||
                    ($chosen->type != 'claimed' && $chosen->source_year > $row->source_year)) {
                $chosen = $ticket;
            }
        }

        if ($chosen) {
            $chosen->selected = 1;
        }

        $row = $rows->firstWhere('type', 'vehicle_pass');
        if ($row) {
            $vp = [
                'id'     => $row->id,
                'type'  => $row->type,
                'status' => $row->status,
             ];
        } else {
            $vp = null;
        }

        $row = $rows->firstWhere('type', 'work_access_pass');
        if ($row) {
            $wap = [
                'id'              => $row->id,
                'type'             => $row->type,
                'status'          => $row->status,
                'access_any_time' => $row->access_any_time,
                'access_date'     => (string)$row->access_date,
            ];
        } else {
            $wap = null;
        }

        $wapso = $rows->where('type', 'work_access_pass_so')->map(function ($so) {
            return $this->buildSOWAPEntry($so);
        })->values()->all();

        $year = setting('YrTicketThreshold') - 1;
        $credits = Timesheet::earnedCreditsForYear($person->id, $year);

        $package = [
            'tickets'        => $tickets,
            'vehicle_pass'   => $vp,
            'wap'            => $wap,
            'wapso'          => $wapso,
            'year_earned'    => $year,
            'credits_earned' => $credits,
        ];

        if ($period == 'open' || $period == 'closed') {
            $row = AccessDocumentDelivery::findForPersonYear($person->id, current_year());

            if ($row) {
                $package['delivery'] = [
                    'method'      => $row->method,
                    'street'      => $row->street,
                    'city'        => $row->city,
                    'state'       => $row->state,
                    'postal_code' => $row->postal_code,
                    'country'     => $row->country,
                ];
            } else {
                $package['delivery'] = [ 'method' => 'none' ];
            }
        }

        return response()->json([ 'package' => $package ]);
    }

    /*
     * Update the SO WAP list
     */

    public function storeWAPSO(Person $person)
    {
        $personId = $person->id;
        $this->authorize('storeSOSWAP', [ AccessDocument::class, $personId ]);

        $params = request()->validate([
           'names'        => 'present|array|max:10',
           'names.*.id'   => 'sometimes|nullable',
           'names.*.name' => 'sometimes|nullable',
        ]);

        $maxSO = setting('TAS_WAPSOMax');

        $documents = [];
        $year = current_year();

        foreach ($params['names'] as $row) {
            $soName = trim($row['name']);
            $soId = $row['id'];

            if ($soId == 'new') {
                // New SO pass is being asked for
                if (empty($soName)) {
                    throw new \InvalidArgumentException('New SO WAP pass requested but no name given.');
                }

                // Make sure the max. has not been hit already
                if (AccessDocument::SOWAPCount($personId, $year) >= $maxSO) {
                    throw new \InvalidArgumentException('New pass would exceed the limit of '.$maxSO.' allowed SO WAP passes.');
                }

                // Looks good, create it
                $accessDocument = AccessDocument::createSOWAP($personId, $year, $soName);
                AccessDocumentChanges::log($accessDocument, $this->user->id, $accessDocument, 'create');
                $documents[] = [ 'id' => $accessDocument->id, 'name' => $soName ];
            } else {
                // Find the existing record
                $wap = AccessDocument::findForPerson($personId, $soId);

                if (empty($soName)) {
                    // Cancel the record
                    $wap->status = 'cancelled';
                } else {
                    // update the name, and claim the pass
                    $wap->status = 'claimed';
                    $wap->name = $soName;
                }

                $changes = $wap->getChangedValues();
                $dirty = $wap->getDirty();
                $isNew = $wap->id == null;
                if (!empty($dirty)) {
                    $wap->save();
                    $dirty['id'] = $wap->id;
                    $documents[] = $dirty;

                    if ($isNew) {
                        AccessDocumentChanges::log($wap, $this->user->id, $wap, 'create');
                    } else {
                        AccessDocumentChanges::log($wap, $this->user->id, $changes);
                    }
                }
            }
        }

        if (!empty($documents)) {
            $this->log('access-document-wap-so', 'Updated list', $documents, $personId);
        }

        $rows = AccessDocument::findSOWAPsForPerson($personId, $year);
        // Send back the updated list
        return response()->json([ 'names' => $rows->map(function ($row) { return $this->buildSOWAPEntry($row); }) ]);
    }

    /*
     * Create or update the delivery method and/or address.
     */

    public function delivery(Person $person)
    {
        $this->authorize('delivery', [ AccessDocumentDelivery::class, $person->id ]);

        $params = request()->validate([
            'method'      => 'required|string',
            'street'      => 'sometimes|string',
            'city'        => 'sometimes|string',
            'state'       => 'sometimes|string',
            'postal_code' => 'sometimes|string',
            'country'     => 'sometimes|string',
        ]);

        $add = AccessDocumentDelivery::findOrCreateForPersonYear($person->id, current_year());
        $add->fill($params);

        if (!$add->save()) {
            return $this->restError($add);
        }

        return $this->success();
    }

    private function buildSOWAPEntry($row)
    {
        return [
           'id'              => $row->id,
           'type'            => $row->type,
           'status'          => $row->status,
           'name'            => $row->name,
           'access_date'     => (string) $row->access_date,
           'access_any_time' => $row->access_any_time,
        ];
    }
}

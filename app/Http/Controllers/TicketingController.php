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
        return response()->json([
            'ticketing_info' => [
                'period'                 => config('clubhouse.TicketingPeriod'),
                'is_enabled_for_pnv'     => config('clubhouse.TicketsAndStuffEnablePNV'),

                'ticket_status'          => config('clubhouse.TAS_Tickets'),
                'vp_status'              => config('clubhouse.TAS_VP'),
                'wap_status'             => config('clubhouse.TAS_WAP'),
                'wap_so_status'          => config('clubhouse.TAS_WAPSO'),
                'delivery_status'        => config('clubhouse.TAS_Delivery'),
                'wap_so_max'             => config('clubhouse.TAS_WAPSOMax'),
                'box_office_open_date'   => config('clubhouse.TAS_BoxOfficeOpenDate'),
                'wap_default_date'       => config('clubhouse.TAS_DefaultWAPDate'),
                'wap_date_range'         => config('clubhouse.TAS_WAPDateRange'),
                'wap_alpha_default_date' => config('clubhouse.TAS_DefaultAlphaWAPDate'),
                'wap_so_default_date'    => config('clubhouse.TAS_DefaultSOWAPDate'),

                'submit_date'            => config('clubhouse.TAS_SubmitDate'),

                'ticket_vendor_email'    => config('clubhouse.TicketVendorEmail'),
                'ticket_vendor_name'     => config('clubhouse.TicketVendorName'),
                'ranger_ticketing_email' => config('clubhouse.TAS_Email'),

                'rpt_credits'            => config('clubhouse.RpTicketThreshold'),
                'sc_credits'             => config('clubhouse.ScTicketThreshold'),
                'earned_year'            => config('clubhouse.YrTicketThreshold') - 1,
                'upcoming_year'          => config('clubhouse.YrTicketThreshold'),

                'faqs'                   => [
                    'ticketing'          => config('clubhouse.TAS_Ticket_FAQ'),
                    'wap'                => config('clubhouse.TAS_WAP_FAQ'),
                    'vp'                 => config('clubhouse.TAS_VP_FAQ'),
                    'alpha'              => config('clubhouse.TAS_Alpha_FAQ'),
                ]
            ]
        ]);
    }

    /*
     * Retrieve the ticketing package
     */

    public function package(Person $person)
    {
        $period  = config('clubhouse.TicketingPeriod');

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

        $year = config('clubhouse.YrTicketThreshold') - 1;
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
            $row = AccessDocumentDelivery::findForPersonYear($person->id, date('Y'));

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

        $maxSO = config('clubhouse.TAS_WAPSOMax');

        $documents = [];
        $year = date('Y');

        foreach ($params['names'] as $row) {
            $soName = $row['name'];
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

                $dirty = $wap->getDirty();
                if (!empty($dirty)) {
                    $wap->save();
                    $dirty['id'] = $wap->id;
                    $documents[] = $dirty;
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
            'street'      => 'string|required_if:method,mail',
            'city'        => 'string|required_if:method,mail',
            'state'       => 'string|required_if:method,mail',
            'postal_code' => 'string|required_if:method,mail',
            'country'     => 'string|required_if:method,mail',
        ]);

        $add = AccessDocumentDelivery::findOrCreateForPersonYear($person->id, date('Y'));
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

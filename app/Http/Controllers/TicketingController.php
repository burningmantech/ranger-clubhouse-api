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
                'RpTicketThreshold', 'ScTicketThreshold',
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

        $this->authorize('index', [ AccessDocument::class, $person->id ]);

        return response()->json([ 'package' => AccessDocument::buildPackageForPerson($person->id) ]);
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

        $rows = AccessDocument::findSOWAPsForPerson($personId, $year);
        // Send back the updated list
        return response()->json([ 'names' => $rows->map(function ($row) { return AccessDocument::buildSOWAPEntry($row); }) ]);
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
}

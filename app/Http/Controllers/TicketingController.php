<?php

/*
 * TicketingController - a higher level controller which deals directly
 * with a person claiming, and updating details for their ticket package.
 *
 * See AccessDocumentController which handles the CRUD side of things.
 */

namespace App\Http\Controllers;

use App\Exceptions\UnacceptableConditionException;
use App\Lib\TicketAndProvisionsPackage;
use App\Lib\TicketingStatistics;
use App\Models\AccessDocument;
use App\Models\Person;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TicketingController extends ApiController
{
    /**
     * Retrieve the ticketing information
     *
     * @return JsonResponse
     */

    public function ticketingInfo(): JsonResponse
    {
        $settings = setting([
            'ScTicketThreshold',
            'SpTicketThreshold',
            'TAS_Alpha_FAQ',
            'TAS_BoxOfficeOpenDate',
            'TAS_DefaultAlphaWAPDate',
            'TAS_DefaultSOWAPDate',
            'TAS_DefaultWAPDate',
            'TAS_Email',
            'TAS_LSD_PayByDateTime',
            'TAS_PayByDateTime',
            'TAS_Pickup_Locations',
            'TAS_Provisions_FAQ',
            'TAS_SAPDateRange',
            'TAS_SAPSOMax',
            'TAS_SAP_FAQ',
            'TAS_Special_Price_Ticket_Cost',
            'TAS_Special_Price_Vehicle_Pass_Cost',
            'TAS_SubmitDate',
            'TAS_Ticket_FAQ',
            'TAS_VP_FAQ',
            'TicketVendorEmail',
            'TicketVendorName',
            'TicketingPeriod',
            'TicketsAndStuffEnablePNV',
        ]);

        return response()->json([
            'ticketing_info' => [
                'period' => $settings['TicketingPeriod'],
                'is_enabled_for_pnv' => $settings['TicketsAndStuffEnablePNV'],

                'wap_so_max' => $settings['TAS_SAPSOMax'],
                'box_office_open_date' => $settings['TAS_BoxOfficeOpenDate'],
                'wap_default_date' => $settings['TAS_DefaultWAPDate'],
                'wap_date_range' => $settings['TAS_SAPDateRange'],
                'wap_alpha_default_date' => $settings['TAS_DefaultAlphaWAPDate'],
                'wap_so_default_date' => $settings['TAS_DefaultSOWAPDate'],

                'submit_date' => $settings['TAS_SubmitDate'],

                'ticket_vendor_email' => $settings['TicketVendorEmail'],
                'ticket_vendor_name' => $settings['TicketVendorName'],
                'ranger_ticketing_email' => $settings['TAS_Email'],

                'spt_credits' => $settings['SpTicketThreshold'],
                'sc_credits' => $settings['ScTicketThreshold'],

                'pickup_locations' => $settings['TAS_Pickup_Locations'],

                'paid_by' => $settings['TAS_PayByDateTime'],

                'lsd_paid_by' => $settings['TAS_LSD_PayByDateTime'],

                'spt_cost' => $settings['TAS_Special_Price_Ticket_Cost'],
                'sp_vp_cost' => $settings['TAS_Special_Price_Vehicle_Pass_Cost'],

                'faqs' => [
                    'ticketing' => $settings['TAS_Ticket_FAQ'],
                    'wap' => $settings['TAS_SAP_FAQ'],
                    'vp' => $settings['TAS_VP_FAQ'],
                    'alpha' => $settings['TAS_Alpha_FAQ'],
                    'provisions' => $settings['TAS_Provisions_FAQ'],
                ]
            ]
        ]);
    }

    /**
     * Retrieve the ticketing package
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function package(Person $person): JsonResponse
    {
        $this->authorize('index', [AccessDocument::class, $person->id]);
        return response()->json(['package' => TicketAndProvisionsPackage::buildPackageForPerson($person->id)]);
    }

    /**
     * Update the SO WAP list
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException|UnacceptableConditionException
     */

    public function storeWAPSO(Person $person): JsonResponse
    {
        $personId = $person->id;
        $this->authorize('storeSOSWAP', [AccessDocument::class, $personId]);

        $params = request()->validate([
            'names' => 'present|array|max:10',
            'names.*.id' => 'sometimes|nullable',
            'names.*.name' => 'sometimes|nullable',
        ]);

        $maxSO = setting('TAS_SAPSOMax');

        $year = current_year();

        foreach ($params['names'] as $row) {
            $soName = trim($row['name']);
            $soId = $row['id'];

            if ($soId == 'new') {
                // New SO pass is being asked for
                if (empty($soName)) {
                    throw new UnacceptableConditionException('New SO WAP pass requested but no name given.');
                }

                // Make sure the max. has not been hit already
                if (AccessDocument::SOWAPCount($personId) >= $maxSO) {
                    throw new UnacceptableConditionException('New pass would exceed the limit of ' . $maxSO . ' allowed SO WAP passes.');
                }

                // Looks good, create it
                $accessDocument = AccessDocument::createSOWAP($personId, $year, $soName);
            } else {
                // Find the existing record
                $wap = AccessDocument::findForPerson($personId, $soId);

                if (empty($soName)) {
                    // Cancel the record
                    $wap->status = AccessDocument::CANCELLED;
                } else {
                    // update the name, and claim the pass
                    $wap->status = AccessDocument::CLAIMED;
                    $wap->name = $soName;
                }

                $wap->save();
            }
        }

        $rows = AccessDocument::findSOWAPsForPerson($personId);
        // Send back the updated lists
        return response()->json($rows);
    }

    /**
     * Set the delivery method for either:
     *
     * - ALL claimed SPT, Staff Credentials, and Vehicle passes. (tickets + vps are processed together and special
     *   care is taken for Staff Credentials versus SPTs)
     *     OR
     * - Set the delivery method for a single Special Type access document (Gift or LSD)
     *
     * Note: As of 2022, the mailing address is no longer required. The information is
     * collected from the user when checking out thru the main ticketing website.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException|UnacceptableConditionException
     */

    public function delivery(Person $person): JsonResponse
    {
        $params = request()->validate([
            'delivery_method' => 'required|string',
            'street' => 'sometimes|string|nullable',
            'city' => 'sometimes|string|nullable',
            'state' => 'sometimes|string|nullable',
            'postal_code' => 'sometimes|string|nullable',
            'country' => 'sometimes|string|nullable',
            'special_document_id' => 'sometimes|integer|exists:access_document,id'
        ]);

        $specialTicketId = $params['special_ticket_id'] ?? null;
        if ($specialTicketId) {
            $specialTicket = AccessDocument::findOrFail($specialTicketId);
            $this->authorize('deliverySpecialDocument', $specialTicket);
            if ($specialTicket->isSpecialDocument()) {
                throw new UnacceptableConditionException("Access document is not a Special Type document");
            }
            if ($specialTicket->status != AccessDocument::QUALIFIED && $specialTicketId->status != AccessDocument::CLAIMED) {
                throw new UnacceptableConditionException("Access document is not qualified nor claimed.");
            }
            $tickets = [$specialTicket];
            unset($params['special_document_id']);
        } else {
            $this->authorize('delivery', [AccessDocument::class, $person->id]);
            $tickets = AccessDocument::findAllAvailableDeliverablesForPerson($person->id);
        }

        foreach ($tickets as $ticket) {
            $ticket->fill($params);
            $ticket->auditReason = 'Delivery update';
            $ticket->save();
        }

        return $this->success($tickets, null, 'access_document');
    }

    /**
     * Return the appreciation credit/hour thresholds for tickets, All-You-Can-Eat pass, shirts, and showers.
     *
     * @return JsonResponse
     */

    public function thresholds(): JsonResponse
    {
        // anyone can see the thresholds.

        $thresholds = setting([
            'AllYouCanEatEventWeekThreshold',
            'AllYouCanEatEventPeriodThreshold',
            'ScTicketThreshold',
            'SpTicketThreshold',
            'ShowerAccessEntireEventThreshold',
            'ShowerAccessEventWeekThreshold',
            'ShirtLongSleeveHoursThreshold',
            'ShirtShortSleeveHoursThreshold',
        ]);

        return response()->json(['thresholds' => $thresholds]);
    }

    /**
     * Retrieve ticketing statistics
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function statistics(): JsonResponse
    {
        $this->authorize('statistics', AccessDocument::class);

        return response()->json(['statistics' => TicketingStatistics::execute()]);
    }
}

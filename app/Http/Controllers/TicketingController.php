<?php

/*
 * TicketingController - a higher level controller which deals directly
 * with a person claiming, and updating details for their ticket package.
 *
 * See AccessDocumentController which handles the CRUD side of things.
 */

namespace App\Http\Controllers;

use App\Lib\TicketAndProvisionsPackage;
use App\Lib\TicketingStatistics;
use App\Models\AccessDocument;
use App\Models\AccessDocumentChanges;
use App\Models\Person;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

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
            'TicketingPeriod', 'TicketsAndStuffEnablePNV',
            'TAS_WAPSOMax',
            'TAS_BoxOfficeOpenDate', 'TAS_DefaultWAPDate', 'TAS_WAPDateRange', 'TAS_DefaultAlphaWAPDate',
            'TAS_DefaultSOWAPDate', 'TAS_SubmitDate',
            'TicketVendorEmail', 'TicketVendorName', 'TAS_Email',
            'RpTicketThreshold', 'ScTicketThreshold',
            'TAS_Ticket_FAQ', 'TAS_WAP_FAQ', 'TAS_VP_FAQ', 'TAS_Alpha_FAQ',
            'TAS_Pickup_Locations',
            'TAS_PayByDateTime',
            'TAS_SpecialSubmitDate'
        ]);

        return response()->json([
            'ticketing_info' => [
                'period' => $settings['TicketingPeriod'],
                'is_enabled_for_pnv' => $settings['TicketsAndStuffEnablePNV'],

                'wap_so_max' => $settings['TAS_WAPSOMax'],
                'box_office_open_date' => $settings['TAS_BoxOfficeOpenDate'],
                'wap_default_date' => $settings['TAS_DefaultWAPDate'],
                'wap_date_range' => $settings['TAS_WAPDateRange'],
                'wap_alpha_default_date' => $settings['TAS_DefaultAlphaWAPDate'],
                'wap_so_default_date' => $settings['TAS_DefaultSOWAPDate'],

                'submit_date' => $settings['TAS_SubmitDate'],
                'special_submit_date' => $settings['TAS_SpecialSubmitDate'],

                'ticket_vendor_email' => $settings['TicketVendorEmail'],
                'ticket_vendor_name' => $settings['TicketVendorName'],
                'ranger_ticketing_email' => $settings['TAS_Email'],

                'rpt_credits' => $settings['RpTicketThreshold'],
                'sc_credits' => $settings['ScTicketThreshold'],

                'pickup_locations' => $settings['TAS_Pickup_Locations'],

                'paid_by' => $settings['TAS_PayByDateTime'],

                'faqs' => [
                    'ticketing' => $settings['TAS_Ticket_FAQ'],
                    'wap' => $settings['TAS_WAP_FAQ'],
                    'vp' => $settings['TAS_VP_FAQ'],
                    'alpha' => $settings['TAS_Alpha_FAQ'],
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
     * @throws AuthorizationException
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

        $maxSO = setting('TAS_WAPSOMax');

        $documents = [];
        $year = current_year();

        foreach ($params['names'] as $row) {
            $soName = trim($row['name']);
            $soId = $row['id'];

            if ($soId == 'new') {
                // New SO pass is being asked for
                if (empty($soName)) {
                    throw new InvalidArgumentException('New SO WAP pass requested but no name given.');
                }

                // Make sure the max. has not been hit already
                if (AccessDocument::SOWAPCount($personId) >= $maxSO) {
                    throw new InvalidArgumentException('New pass would exceed the limit of ' . $maxSO . ' allowed SO WAP passes.');
                }

                // Looks good, create it
                $accessDocument = AccessDocument::createSOWAP($personId, $year, $soName);
                AccessDocumentChanges::log($accessDocument, $this->user->id, $accessDocument, 'create');
                $documents[] = ['id' => $accessDocument->id, 'name' => $soName];
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

        $rows = AccessDocument::findSOWAPsForPerson($personId);
        // Send back the updated lists
        return response()->json($rows);
    }

    /**
     * Update the delivery method for all available tickets
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function delivery(Person $person): JsonResponse
    {
        $this->authorize('delivery', [AccessDocument::class, $person->id]);

        $params = request()->validate([
            'delivery_method' => 'required|string',
            'street' => 'sometimes|string|nullable',
            'city' => 'sometimes|string|nullable',
            'state' => 'sometimes|string|nullable',
            'postal_code' => 'sometimes|string|nullable',
            'country' => 'sometimes|string|nullable',
        ]);

        $tickets = AccessDocument::findAllAvailableDeliverablesForPerson($person->id);
        foreach ($tickets as $ticket) {
            $ticket->fill($params);
            $ticket->auditReason = 'Delivery update';
            $changes = $ticket->getChangedValues();
            if (!empty($changes)) {
                $ticket->save();
                AccessDocumentChanges::log($ticket, $this->user->id, $changes);
            }
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
            'RpTicketThreshold',
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

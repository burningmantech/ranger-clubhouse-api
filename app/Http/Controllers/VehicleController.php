<?php

namespace App\Http\Controllers;

use App\Lib\MVR;
use App\Lib\Reports\VehiclePaperworkReport;
use App\Models\Document;
use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\Vehicle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class VehicleController extends ApiController
{
    /**
     * Display a listing of the person vehicles.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $params = request()->validate([
            'person_id' => 'sometimes|integer',
            'event_year' => 'sometimes|integer',
            'status' => 'sometimes|string',
            'license_number' => 'sometimes|string',
            'sticker_number' => 'sometimes|string',
            'number' => 'sometimes|string', // search sticker, license, or rental #
        ]);

        if (isset($params['person_id'])) {
            $this->authorize('indexForPerson', [Vehicle::class, $params['person_id']]);
        } else {
            $this->authorize('index', Vehicle::class);
        }

        return $this->toRestFiltered(Vehicle::findForQuery($params), null, 'vehicle');
    }

    /**
     * Create a person vehicle
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function store(): JsonResponse
    {
        $vehicle = new Vehicle;
        $this->fromRestFiltered($vehicle);
        $this->authorize('storeForPerson', $vehicle);

        if ($vehicle->save()) {
            $vehicle->loadRelationships();
            return $this->toRestFiltered($vehicle);
        }

        return $this->restError($vehicle);
    }

    /**
     * Display the specified resource.
     * @param Vehicle $vehicle
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('show', $vehicle);
        $vehicle->loadRelationships();
        return $this->toRestFiltered($vehicle);
    }

    /**
     * Update the specified resource in storage.
     * @param Vehicle $vehicle
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function update(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);
        $this->fromRest($vehicle);

        if ($vehicle->save()) {
            $vehicle->loadRelationships();
            return $this->toRestFiltered($vehicle);
        }

        return $this->restError($vehicle);
    }

    /**
     * Delete a person vehicle record.
     *
     * @param Vehicle $vehicle
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('delete', $vehicle);
        $vehicle->delete();
        return $this->restDeleteSuccess();
    }

    /**
     * Vehicle Paperwork Report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function paperwork(): JsonResponse
    {
        $this->authorize('paperwork', [Vehicle::class]);

        return response()->json(['people' => VehiclePaperworkReport::execute()]);
    }

    /**
     * Retrieve the vehicle request configuration for the person
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function info(Person $person): JsonResponse
    {
        $this->authorize('info', [ Vehicle::class, $person]);
        $personId = $person->id;
        $event = PersonEvent::findForPersonYear($personId, current_year());

        if (!in_array($person->status, Person::ACTIVE_STATUSES) && !$person->isPNV()) {
            $info = [];
        } else {
            $info = [
                'motorpool_agreement_available' => setting('MotorpoolPolicyEnable'),
                'motorpool_agreement_signed' => $event->signed_motorpool_agreement,
                'personal_vehicle_signed' => $event->signed_personal_vehicle_agreement,
                'org_vehicle_insurance' => $event->org_vehicle_insurance,
                'vehicle_requests_allowed' => $event->may_request_stickers,
            ];

            if ($info['motorpool_agreement_available']) {
                $info['motorpool_agreement_tag'] = Document::MOTORPOOL_POLICY_TAG;
            }

            if ($event->may_request_stickers) {
                $info['personal_vehicle_document_url'] = setting('RangerPersonalVehiclePolicyUrl');
                $info['personal_vehicle_agreement_tag'] = Document::PERSONAL_VEHICLE_AGREEMENT_TAG;
            }

            if (MVR::isEligible($personId, $event)) {
                $info['mvr_eligible'] = true;
                $info['mvr_request_url'] = setting('MVRRequestFormURL');
            } else {
                $info['mvr_eligible'] = false;
            }
        }

        return response()->json(['info' => $info]);
    }
}

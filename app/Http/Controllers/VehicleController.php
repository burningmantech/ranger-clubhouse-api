<?php

namespace App\Http\Controllers;

use App\Lib\MVR;
use App\Lib\PVR;
use App\Lib\Reports\VehiclePaperworkReport;
use App\Models\Document;
use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\PersonSlot;
use App\Models\PersonTeam;
use App\Models\Position;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

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
        $this->authorize('info', [Vehicle::class, $person]);
        $params = request()->validate([
            'include_eligible_teams' => 'boolean|sometimes'
        ]);

        $personId = $person->id;
        $year = current_year();

        $event = PersonEvent::findForPersonYear($personId, $year);

        if (!in_array($person->status, Person::ACTIVE_STATUSES)
            && !$person->isPNV()
            && $person->status != Person::NON_RANGER) {
            $info = [];
        } else {
            list ($deadline, $pastDeadline) = MVR::retrieveDeadline();
            $info = [
                'motorpool_agreement_available' => setting('MotorPoolProtocolEnabled'),
                'motorpool_agreement_signed' => $event?->signed_motorpool_agreement,

                'mvr_positions' => Position::vehicleEligibleForPerson('mvr', $personId),
                'mvr_signups' => PersonSlot::retrieveMVRSignups($personId, $year),
                'ignore_mvr' => $event?->ignore_mvr ?? false,
                'org_vehicle_insurance' => $event?->org_vehicle_insurance,

                'ignore_pvr' => $event?->ignore_pvr ?? false,
                'personal_vehicle_signed' => $event?->signed_personal_vehicle_agreement,
                'pvr_positions' => Position::vehicleEligibleForPerson('pvr', $personId),
                'mvr_deadline' => $deadline,
                'mvr_past_deadline' => $pastDeadline,
            ];

            if ($params['include_eligible_teams'] ?? false) {
                $info['mvr_teams'] = PersonTeam::retrieveMVREligibleForPerson($personId);
                $info['pvr_teams'] = PersonTeam::retrievePVREligibleForPerson($personId);
            }

            if ($info['motorpool_agreement_available']) {
                $info['motorpool_agreement_tag'] = Document::MOTORPOOL_POLICY_TAG;
            }

            $info['personal_vehicle_document_url'] = setting('RangerPersonalVehiclePolicyUrl');
            $info['personal_vehicle_agreement_tag'] = Document::PERSONAL_VEHICLE_AGREEMENT_TAG;
            if (PVR::isEligible($personId, $event, $year)) {
                $info['pvr_eligible'] = true;
            } else {
                $info['pvr_eligible'] = false;
            }

            if (MVR::isEligible($personId, $event, current_year())) {
                $info['mvr_eligible'] = true;
                $info['mvr_form_instructions_tag'] = Document::MVR_FORM_INSTRUCTIONS_TAG;
            } else {
                $info['mvr_eligible'] = false;
            }
        }

        return response()->json(['info' => $info]);
    }
}

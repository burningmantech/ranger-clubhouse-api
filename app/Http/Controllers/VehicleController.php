<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Auth\Access\AuthorizationException;

class VehicleController extends ApiController
{
    /**
     * Display a listing of the person vehicles.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
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
            $this->authorize('indexForPerson', [Vehicle::class, $params['person_id'] ]);
        } else {
            $this->authorize('index', Vehicle::class);
        }

        return $this->toRestFiltered(Vehicle::findForQuery($params), null, 'vehicle');
    }

    /**
     * Create a person vehicle
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws AuthorizationException
     */
    public function store()
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
     * @return \Illuminate\Http\JsonResponse
     * @throws AuthorizationException
     */

    public function show(Vehicle $vehicle)
    {
        $this->authorize('show', $vehicle);
        $vehicle->loadRelationships();
        return $this->toRestFiltered($vehicle);
    }

    /**
     * Update the specified resource in storage.
     * @param Vehicle $vehicle
     * @return \Illuminate\Http\JsonResponse
     * @throws AuthorizationException
     */

    public function update(Vehicle $vehicle)
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
     * @return \Illuminate\Http\JsonResponse
     * @throws AuthorizationException
     */
    public function destroy(Vehicle $vehicle)
    {
        $this->authorize('delete', $vehicle);
        $vehicle->delete();
        return $this->restDeleteSuccess();
    }
}

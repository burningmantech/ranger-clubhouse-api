<?php

namespace App\Http\Controllers;

use App\Models\HandleReservation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class HandleReservationController extends ApiController
{

    /**
     * Display a listing of handle reservations.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', HandleReservation::class);
        return $this->success(HandleReservation::findAll(), null, 'handle_reservation');
    }

    /**
     * Store a newly created handle reservation in the database.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function store(): JsonResponse
    {
        $this->authorize('create', HandleReservation::class);
        $handleReservation = new HandleReservation;
        $this->fromRest($handleReservation);

        if ($handleReservation->save()) {
            return $this->success($handleReservation);
        }

        return $this->restError($handleReservation);
    }

    /**
     * Display the specified handle reservation.
     *
     * @param HandleReservation $handleReservation
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function show(HandleReservation $handleReservation): JsonResponse
    {
        $this->authorize('view', HandleReservation::class);
        return $this->success($handleReservation);
    }

    /**
     * Update the specified handle reservation in the database.
     *
     * @param HandleReservation $handleReservation
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function update(HandleReservation $handleReservation): JsonResponse
    {
        $this->authorize('update', HandleReservation::class);
        $this->fromRest($handleReservation);

        if ($handleReservation->save()) {
            return $this->success($handleReservation);
        }

        return $this->restError($handleReservation);
    }

    /**
     * Remove the specified handle reservation from the database.
     *
     * @param HandleReservation $handleReservation
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function destroy(HandleReservation $handleReservation): JsonResponse
    {
        $this->authorize('delete', HandleReservation::class);
        $handleReservation->delete();
        return $this->restDeleteSuccess();
    }
}

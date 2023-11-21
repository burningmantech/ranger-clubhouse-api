<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\AlertPerson;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AlertController extends ApiController
{
    /**
     * Return all Alert records
     *
     * @return JsonResponse
     */

    public function index(): JsonResponse
    {
        return $this->success(Alert::findAll());
    }

    /**
     * Create a new alert.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws ValidationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', [Alert::class]);

        $alert = new Alert;
        $this->fromRest($alert);

        if ($alert->save()) {
            return $this->success($alert);
        }

        return $this->restError($alert);
    }

    /**
     * Display a single alert
     *
     * @param Alert $alert
     * @return JsonResponse
     */

    public function show(Alert $alert): JsonResponse
    {
        return $this->success($alert);
    }

    /**
     * Update an alert
     *
     * @param Alert $alert
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws ValidationException
     */

    public function update(Alert $alert): JsonResponse
    {
        $this->authorize('update', $alert);

        $this->fromRest($alert);
        if ($alert->save()) {
            return $this->success($alert);
        }

        return $this->restError($alert);
    }

    /**
     * Delete an alert from the system
     *
     * @param Alert $alert
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(Alert $alert): JsonResponse
    {
        $this->authorize('delete', $alert);
        $alert->delete();
        return $this->restDeleteSuccess();
    }
}

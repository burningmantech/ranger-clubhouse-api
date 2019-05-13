<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\AlertPerson;
use App\Http\Controllers\ApiController;

class AlertController extends ApiController
{
    /*
     * Return all Alert records
     */

    public function index()
    {
        return $this->success(Alert::findAll());
    }

    /*
     * Create a new alert.
     */

    public function store()
    {
        $this->authorize('store', [ Alert::class ]);

        $alert = new Alert;
        $this->fromRest($alert);

        if ($alert->save()) {
            return $this->success($alert);
        }

        return $this->restError($alert);
    }

    /*
     * Display a single alert
     */
    public function show(Alert $alert)
    {
        return $this->success($alert);
    }

    /*
     * Update an alert
     */

    public function update(Alert $alert)
    {
        $this->authorize('update', $alert);

        $this->fromRest($alert);
        if ($alert->save()) {
            return $this->success($alert);
        }

        return $this->restError($alert);
    }

    /*
     * Delete an alert from the system
     */

    public function destroy(Alert $alert)
    {
        $this->authorize('delete', $alert);
        $alert->delete();
        AlertPerson::where('alert_id', $alert->id)->delete();
        return $this->restDeleteSuccess();
    }
}

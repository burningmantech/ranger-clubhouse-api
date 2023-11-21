<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\AlertPerson;
use App\Models\Person;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AlertPersonController extends ApiController
{
    /**
     * Return an array of AlertPerson for a person
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(Person $person): JsonResponse
    {
        $this->authorize('view', [AlertPerson::class, $person]);

        return response()->json(['alerts' => AlertPerson::findAllForPerson($person->id)]);
    }

    /**
     * Update all the alerts for a person in bulk.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws ValidationException
     */

    public function update(Person $person): JsonResponse
    {
        $this->authorize('update', [AlertPerson::class, $person]);

        $data = request()->validate([
            'alerts.*.id' => 'required|integer',
            'alerts.*.use_email' => 'required|boolean',
            'alerts.*.use_sms' => 'required|boolean'
        ]);

        $alertChanges = [];

        foreach ($data['alerts'] as $pref) {
            $alert = Alert::find($pref['id']);
            if (!$alert) {
                continue;
            }

            $alertPerson = AlertPerson::findOrCreateForPerson($person->id, $alert->id);
            $alertPerson->use_email = $pref['use_email'];
            $alertPerson->use_sms = $pref['use_sms'];

            $changes = [];
            if ($alertPerson->isDirty('use_email')) {
                $changes['use_email'] = [$alertPerson->getOriginal('use_email'), $alertPerson->use_email];
            }

            if ($alertPerson->isDirty('use_sms')) {
                $changes['use_sms'] = [$alertPerson->getOriginal('use_sms'), $alertPerson->use_sms];
            }

            $alertPerson->save();

            if (!empty($changes)) {
                $changes['alert_id'] = $alert->id;
                $alertChanges[] = $changes;
            }
        }

        if (!empty($alertChanges)) {
            $this->log('person-alerts-update', 'alerts update', ['alerts' => $alertChanges], $person->id);
        }

        return $this->success();
    }
}

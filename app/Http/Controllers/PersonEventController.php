<?php

namespace App\Http\Controllers;

use App\Models\AccessDocument;
use App\Models\Person;
use App\Models\PersonEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

/*
 * Person Event records are special -- a record will always be returned even if the record does not
 * actually exist within the database.
 */

class PersonEventController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $this->authorize('index', PersonEvent::class);

        $params = request()->validate([
            'person_id' => 'sometimes|integer|exists:person,id',
            'year' => 'sometimes|integer'
        ]);

        return $this->toRestFiltered(PersonEvent::findForQuery($params), null, 'person_event');
    }

    /**
     * Display the person event
     *
     * @param PersonEvent $personEvent
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(PersonEvent $personEvent): JsonResponse
    {
        $this->authorize('show', $personEvent);

        return $this->success($personEvent);
    }

    /**
     * Update (or create) a person event record
     *
     * @param PersonEvent $personEvent
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function update(PersonEvent $personEvent): JsonResponse
    {
        $this->authorize('update', $personEvent);
        $this->fromRest($personEvent);

        if (!$personEvent->save()) {
            return $this->restError($personEvent);
        }

        return $this->success($personEvent);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param PersonEvent $personEvent
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(PersonEvent $personEvent): JsonResponse
    {
        if ($personEvent->exists()) {
            $this->authorize('destroy', $personEvent);
            $personEvent->delete();
        }
        return $this->restDeleteSuccess();
    }

    /**
     * Update milestone progress
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function updateProgress(Person $person): JsonResponse
    {
        $this->authorize('updateProgress', [AccessDocument::class, $person]);

        $params = request()->validate([
            'milestone' => ['required', 'string',
                Rule::in(
                    'ticket-visited', 'ticket-started', 'ticket-finished',
                    'pii-started', 'pii-finished',
                )
            ],
        ]);

        $pe = PersonEvent::findForPersonYear($person->id, current_year());
        switch ($params['milestone']) {
            case 'ticket-visited':
                $pe->ticketing_last_visited_at = now();
                break;
            case 'ticket-started':
                $pe->ticketing_started_at = now();
                break;
            case 'ticket-finished':
                $pe->ticketing_finished_at = now();
                break;
            case 'pii-started':
                $pe->pii_started_at = now();
                break;
            case 'pii-finished':
                $pe->pii_finished_at = now();
                break;
        }

        $pe->saveWithoutValidation();

        return $this->success();
    }


}

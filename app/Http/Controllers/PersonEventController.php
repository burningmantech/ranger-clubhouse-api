<?php

namespace App\Http\Controllers;

use App\Models\PersonEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

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
}

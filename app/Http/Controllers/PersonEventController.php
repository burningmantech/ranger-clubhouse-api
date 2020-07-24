<?php

namespace App\Http\Controllers;

use App\Models\PersonEvent;
use App\Models\Role;

use Illuminate\Http\Request;

/*
 * Person Event records are special -- a record will always be returned even if the record does not
 * actually exist within the database.
 */

class PersonEventController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index()
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
     * @param  \App\Models\PersonEvent  $personEvent
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show(PersonEvent $personEvent)
    {
        $this->authorize('show', $personEvent);

        return $this->success($personEvent);
    }

    /**
     * Update (or create) a person event record
     *
     * @param PersonEvent $personEvent
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */

    public function update(PersonEvent $personEvent)
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
     * @param  \App\Models\PersonEvent  $personEvent
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(PersonEvent $personEvent)
    {
        if ($personEvent->exists()) {
            $this->authorize('destroy', $personEvent);
            $personEvent->delete();
        }
        return $this->restDeleteSuccess();
    }
}

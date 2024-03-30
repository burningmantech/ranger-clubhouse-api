<?php

namespace App\Http\Controllers;

use App\Models\PersonFka;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PersonFkaController extends ApiController
{
    /**
     * Retrieve a list of records based on a criteria
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $params = request()->validate([
            'person_id' => 'required|integer|exists:person,id'
        ]);

        $this->authorize('index', [PersonFka::class, $params['person_id']]);

        return $this->success(PersonFka::findForQuery($params), null, 'person_fka');
    }

    /***
     * Create a person fka record
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function store(): JsonResponse
    {
        $person_fka = new PersonFka;
        $this->fromRest($person_fka);

        $this->authorize('store', $person_fka);

        if ($person_fka->save()) {
            return $this->success($person_fka);
        }

        return $this->restError($person_fka);
    }

    /**
     * Update the specified person fka in storage.
     *
     * @param PersonFka $person_fka
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function update(PersonFka $person_fka): JsonResponse
    {
        $this->authorize('update', $person_fka);
        $this->fromRest($person_fka);

        if ($person_fka->save()) {
            return $this->success($person_fka);
        }

        return $this->restError($person_fka);
    }

    /**
     * Delete a person fka record
     *
     * @param PersonFka $person_fka
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(PersonFka $person_fka): JsonResponse
    {
        $this->authorize('destroy', $person_fka);
        $person_fka->delete();
        return $this->restDeleteSuccess();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\PersonPog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PersonPogController extends ApiController
{
    /**
     * Show the pogs for folks
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $params = request()->validate([
            'person_id' => 'sometimes|integer',
            'pog_id' => 'sometimes|integer',
            'year' => 'sometimes|integer',
        ]);

        $this->authorize('index', [PersonPog::class, $params['person_id'] ?? null]);

        return $this->success(PersonPog::findForQuery($params), null, 'person_pog');
    }

    /**
     * Show a single pog
     *
     * @param PersonPog $personPog
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(PersonPog $personPog): JsonResponse
    {
        $this->authorize('show', $personPog);
        $personPog->loadRelationships();
        return $this->success($personPog);
    }

    /**
     * Create a person pog
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', PersonPog::class);

        $personPog = new PersonPog();
        $this->fromRest($personPog);
        $personPog->issued_by_id = $this->user->id;

        if ($personPog->save()) {
            $personPog->loadRelationships();
            return $this->success($personPog);
        }

        return $this->restError($personPog);
    }

    /**
     * Update an pog
     *
     * @param PersonPog $personPog
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function update(PersonPog $personPog): JsonResponse
    {
        $this->authorize('update', $personPog);
        $this->fromRest($personPog);

        if ($personPog->save()) {
            $personPog->loadRelationships();
            return $this->success($personPog);
        }

        return $this->restError($personPog);
    }

    /**
     * Delete an pog grant
     *
     * @param PersonPog $personPog
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(PersonPog $personPog): JsonResponse
    {
        $this->authorize('destroy', $personPog);

        $personPog->delete();

        return $this->restDeleteSuccess();
    }

    public function config() : JsonResponse
    {
        return response()->json([
            'config' => [
                'meal_half_pog_enabled' => setting('MealHalfPogEnabled'),
                'shower_pog_threshold' => setting('ShowerPogThreshold'),
            ],
        ]);
    }
}

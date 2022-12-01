<?php

namespace App\Http\Controllers;

use App\Models\PersonAward;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PersonAwardController extends ApiController
{
    /**
     * Show the certifications for folks
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $params = request()->validate(
            ['person_id' => 'sometimes|integer'],
            ['award_id' => 'sometimes|integer']
        );

        $this->authorize('index', [PersonAward::class, $params['person_id'] ?? null]);

        return $this->success(PersonAward::findForQuery($params), null, 'person_award');
    }

    /**
     * Show a single award
     *
     * @param PersonAward $personAward
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(PersonAward $personAward): JsonResponse
    {
        $this->authorize('show', $personAward);
        $personAward->loadRelationships();
        return $this->success($personAward);
    }

    /**
     * Create a person award
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', PersonAward::class);

        $personAward = new PersonAward();
        $this->fromRest($personAward);

        if ($personAward->save()) {
            $personAward->loadRelationships();
            return $this->success($personAward);
        }

        return $this->restError($personAward);
    }

    /**
     * Update an award
     *
     * @param PersonAward $personAward
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function update(PersonAward $personAward): JsonResponse
    {
        $this->authorize('update', $personAward);
        $this->fromRest($personAward);

        if ($personAward->save()) {
            $personAward->loadRelationships();
            return $this->success($personAward);
        }

        return $this->restError($personAward);
    }

    /**
     * Delete an award grant
     *
     * @param PersonAward $personAward
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(PersonAward $personAward): JsonResponse
    {
        $this->authorize('destroy', $personAward);

        $personAward->delete();

        return $this->restDeleteSuccess();
    }
}

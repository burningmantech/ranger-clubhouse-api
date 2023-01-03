<?php

namespace App\Http\Controllers;

use App\Models\PersonCertification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PersonCertificationController extends ApiController
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
            ['certification_id' => 'sometimes|integer']
        );

        $this->authorize('index', [PersonCertification::class, $params['person_id'] ?? null]);

        return $this->success(PersonCertification::findForQuery($params), null, 'person_certification');
    }

    /**
     * Show a single certification
     *
     * @param PersonCertification $personCertification
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(PersonCertification $personCertification): JsonResponse
    {
        $this->authorize('show', $personCertification);
        $personCertification->loadRelationships();
        return $this->success($personCertification);
    }

    /**
     * Create a person certification
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', [PersonCertification::class, request()->input('person_certification.person_id')]);

        $personCertification = new PersonCertification();
        $this->fromRest($personCertification);
        $personCertification->recorder_id = Auth::id();

        if ($personCertification->save()) {
            $personCertification->loadRelationships();
            return $this->success($personCertification);
        }

        return $this->restError($personCertification);
    }

    /**
     * Update a certification
     *
     * @param PersonCertification $personCertification
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws ValidationException
     */

    public function update(PersonCertification $personCertification): JsonResponse
    {
        $this->authorize('update', $personCertification);
        $this->fromRest($personCertification);

        if ($personCertification->save()) {
            $personCertification->loadRelationships();
            return $this->success($personCertification);
        }

        return $this->restError($personCertification);
    }

    /**
     * Delete a certification
     *
     * @param PersonCertification $personCertification
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(PersonCertification $personCertification): JsonResponse
    {
        $this->authorize('destroy', $personCertification);

        $personCertification->delete();

        return $this->restDeleteSuccess();
    }
}

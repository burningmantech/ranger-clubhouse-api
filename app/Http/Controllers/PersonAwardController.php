<?php

namespace App\Http\Controllers;

use App\Lib\AwardManagement;
use App\Lib\BulkGrantAward;
use App\Models\Person;
use App\Models\PersonAward;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class PersonAwardController extends ApiController
{
    /**
     * Show the awards for folks
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $params = request()->validate([
            'person_id' => 'sometimes|integer',
            'award_id' => 'sometimes|integer',
            'team_id' => 'sometimes|integer',
        ]);

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
     * @throws AuthorizationException
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

    /**
     * Bulk upload callsigns to be given awards.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function bulkGrant(): JsonResponse
    {
        $this->authorize('bulkGrant', PersonAward::class);

        $params = request()->validate([
            'lines' => 'required|string',
            'commit' => 'required|boolean',
        ]);

        return response()->json(['records' => BulkGrantAward::upload($params['lines'], $params['commit'])]);
    }

    /**
     * Show the awards for a given person.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function awardsForPerson(Person $person): JsonResponse
    {
        $this->authorize('awardsForPerson', [PersonAward::class, $person]);
        return response()->json(PersonAward::retrieveForPerson($person->id));
    }

    /**
     * Show the awards for a given person.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function rebuildPerson(Person $person): JsonResponse
    {
        $this->authorize('rebuildPerson', [PersonAward::class, $person]);
        AwardManagement::rebuildForPersonId($person->id);
        return $this->success();
    }

    /**
     * Rebuild all the awards for everyone.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function rebuildAllAwards(): JsonResponse
    {
        $this->authorize('rebuildAllAwards', PersonAward::class);
        AwardManagement::rebuildAll();
        return $this->success();
    }
}

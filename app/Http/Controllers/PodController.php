<?php

namespace App\Http\Controllers;

use App\Exceptions\UnacceptableConditionException;
use App\Models\Person;
use App\Models\PersonPod;
use App\Models\Pod;
use App\Models\Timesheet;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PodController extends ApiController
{
    /**
     * Return a set of pods
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $this->authorize('index', Pod::class);

        $params = request()->validate([
            'year' => 'sometimes|integer',
            'type' => 'sometimes|string',
            'slot_id' => 'sometimes|integer',
            'include_people' => 'sometimes|boolean',
        ]);

        return $this->success(Pod::findForQuery($params), null, 'pod');
    }

    /**
     * Create a new pod
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', Pod::class);

        $pod = new Pod;
        $this->fromRest($pod);

        if (!$pod->save()) {
            return $this->restError($pod);
        }

        $pod->people = [];
        return $this->success($pod);
    }

    /**
     * Create an Alpha Set (mentor, mitten, and alpha linked together)
     *
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws ValidationException
     */

    public function createAlphaSet(): JsonResponse
    {
        $this->authorize('createAlphaSet', Pod::class);
        $params = request()->validate([
            'slot_id' => 'required|integer|exists:slot,id'
        ]);

        $slotId = $params['slot_id'];
        DB::beginTransaction();
        try {
            $mentorPod = new Pod([
                'slot_id' => $slotId,
                'type' => Pod::TYPE_MENTOR,
                'sort_index' => (Pod::where('slot_id', $slotId)->max('sort_index') ?? 0) + 1,
            ]);
            $mentorPod->saveOrThrow();
            $mentorPod->people = [];
            $alphaPod = new Pod([
                'slot_id' => $slotId,
                'type' => Pod::TYPE_ALPHA,
                'sort_index' => 1,
                'mentor_pod_id' => $mentorPod->id,
            ]);
            $alphaPod->saveOrThrow();
            $alphaPod->people = [];
            $mittenPod = new Pod([
                'slot_id' => $slotId,
                'type' => Pod::TYPE_MITTEN,
                'sort_index' => 1,
                'mentor_pod_id' => $mentorPod->id,
            ]);
            $mittenPod->saveOrThrow();
            $mittenPod->people = [];
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            throw $e;
        }

        return response()->json(['mentor' => $mentorPod, 'mitten' => $mittenPod, 'alpha' => $alphaPod]);
    }

    /**
     * Show a pod
     *
     * @param Pod $pod
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(Pod $pod): JsonResponse
    {
        $this->authorize('show', $pod);
        $pod->load(Pod::RELATIONSHIPS);
        $pod->loadPhotos();
        return $this->success($pod);
    }

    /**
     * Update a pod
     *
     * @param Pod $pod
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function update(Pod $pod): JsonResponse
    {
        $this->authorize('update', $pod);
        $this->fromRest($pod);
        if (!$pod->save()) {
            return $this->restError($pod);
        }

        $pod->load(Pod::RELATIONSHIPS);
        $pod->loadPhotos();

        return $this->success($pod);
    }

    /**
     * Delete a pod, and associated people
     *
     * @param Pod $pod
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(Pod $pod): JsonResponse
    {
        $this->authorize('destroy', $pod);

        $pod->delete();
        PersonPod::where('pod_id', $pod->id)->delete();

        return $this->restDeleteSuccess();
    }

    /**
     * Add a person to a pod
     *
     * @param Pod $pod
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws UnacceptableConditionException
     */

    public function addPerson(Pod $pod): JsonResponse
    {
        $this->authorize('addPerson', $pod);

        $params = request()->validate([
            'is_lead' => 'sometimes|bool',
            'person_id' => 'required|integer|exists:person,id',
        ]);

        $person = Person::findOrFail($params['person_id']);
        $personId = $person->id;
        $personPod = PersonPod::findCurrentPersonPod($person->id, $pod->id);
        if ($personPod) {
            throw new UnacceptableConditionException("Person is already in the pod");
        }

        $timesheet = Timesheet::findPersonOnDuty($personId);
        $count = PersonPod::currentMemberCount($pod->id) + 1;
        $personPod = new PersonPod([
            'person_id' => $personId,
            'pod_id' => $pod->id,
            'is_lead' => $params['is_lead'] ?? false,
            'timesheet_id' => $timesheet?->id,
            'sort_index' => ($pod->type == Pod::TYPE_ALPHA) ? $count : 1,
        ]);

        $personPod->save();
        $pod->person_count = $count;
        if ($pod->disbanded_at) {
            // Reform the pod.
            $pod->disbanded_at = null;
        }
        $pod->saveWithoutValidation();
        $pod->load(Pod::RELATIONSHIPS);
        $pod->loadPhotos();
        return $this->success($pod);
    }

    /**
     * Update the person's info
     *
     * @param Pod $pod
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws UnacceptableConditionException
     */

    public function updatePerson(Pod $pod): JsonResponse
    {
        $this->authorize('updatePerson', $pod);

        $params = request()->validate([
            'is_lead' => 'sometimes|bool',
            'sort_index' => 'sometimes|integer',
            'person_id' => 'required|integer|exists:person,id',
        ]);

        $person = Person::findOrFail($params['person_id']);
        $personPod = PersonPod::findCurrentPersonPod($person->id, $pod->id);
        if (!$personPod) {
            throw new UnacceptableConditionException("Person is not in the pod");
        }

        if ($pod->type != Pod::TYPE_ALPHA && isset($params['is_lead'])) {
            $personPod->is_lead = $params['is_lead'];
        }

        if (isset($params['sort_index'])) {
            $personPod->sort_index = $params['sort_index'];
        }
        $personPod->save();
        $pod->load(Pod::RELATIONSHIPS);
        $pod->loadPhotos();
        return $this->success($pod);
    }

    /**
     * Move the person from one pod to another
     *
     * @param Pod $oldPod
     * @param Person $person
     * @param Pod $newPod
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws UnacceptableConditionException
     */

    public function movePerson(Pod $oldPod, Person $person, Pod $newPod): JsonResponse
    {
        $this->authorize('movePerson', $oldPod);

        $this->removePersonFromPod($person, $oldPod);

        $timesheet = Timesheet::findPersonOnDuty($person->id);

        $newPersonPod = new PersonPod([
            'person_id' => $person->id,
            'pod_id' => $newPod->id,
            'timesheet_id' => $timesheet?->id,
        ]);
        $newPersonPod->load('person:id,callsign');

        $newPod->person_pod[] = $newPersonPod;
        if ($newPod->type == Pod::TYPE_SHIFT) {
            $people = $newPod->person_pod->sort(fn($a, $b) => strcasecmp($a->person->callsign, $b->person->callsign));
        } else {
            $people = $newPod->person_pod;
        }

        $index = 1;
        foreach ($people as $i => $podling) {
            $podling->sort_index = $index;
            $index++;
            $podling->saveWithoutValidation();
        }

        $newPod->person_count = count($newPod->people);
        if ($newPod->disbanded_at) {
            // Reform the pod.
            $newPod->disbanded_at = null;
        }
        $newPod->saveWithoutValidation();

        return $this->success();
    }


    /**
     * Remove an active person from a pod.
     *
     * @param Pod $pod
     * @return JsonResponse
     * @throws AuthorizationException|UnacceptableConditionException
     */

    public function removePerson(Pod $pod): JsonResponse
    {
        $this->authorize('removePerson', $pod);

        $params = request()->validate([
            'person_id' => 'required|integer',
        ]);

        $person = Person::findOrFail($params['person_id']);
        $this->removePersonFromPod($person, $pod);
        $pod->load(Pod::RELATIONSHIPS);
        $pod->loadPhotos();
        return $this->success($pod);
    }

    private function removePersonFromPod(Person $person, Pod $pod): void
    {
        $personPod = PersonPod::findCurrentPersonPod($person->id, $pod->id);
        if (!$personPod) {
            throw new UnacceptableConditionException("Person is not in the pod");
        }

        $personPod->removed_at = now();
        $personPod->saveWithoutValidation();

        $pod->person_count = PersonPod::currentMemberCount($pod->id);
        if (!$pod->person_count) {
            // Disband the pod
            $pod->disbanded_at = now();
            $pod->saveWithoutValidation();
        }

        for ($idx = 0; $idx < count($pod->people); $idx++) {
            $oldPerson = $pod->people[$idx];
            $sortIdx = $idx + 1;
            if ($oldPerson->sort_index != $sortIdx) {
                // $oldPerson to send back.
                $person->sort_index = $sortIdx;
                $pp = PersonPod::findCurrentPersonPod($oldPerson->id, $pod->id);
                if ($pp) {
                    $pp->sort_index = $sortIdx;
                    $pp->saveWithoutValidation();
                }
            }
        }
    }
}

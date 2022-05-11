<?php

namespace App\Http\Controllers;

use App\Lib\Alpha;
use App\Lib\ProspectiveNewVolunteer;
use App\Models\Person;
use App\Models\PersonMentor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class MentorController extends ApiController
{
    /**
     * Retrieve all potential alphas
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function mentees(): JsonResponse
    {
        $this->authorize('isMentor');
        $params = request()->validate([
            'exclude_bonks' => 'sometimes|boolean',
            'have_training' => 'sometimes|boolean',
            'year' => 'sometimes|integer',
            'person_id' => 'sometimes|integer'
        ]);

        $excludeBonks = $params['exclude_bonks'] ?? false;
        $year = $params['year'] ?? current_year();
        $haveTraining = $params['have_training'] ?? false;

        $personId = $params['person_id'] ?? null;
        if ($personId) {
            $person = Person::findOrFail($personId);
            return response()->json(['mentee' => Alpha::buildAlphaInformation(collect([$person]), $year)[0]]);
        }

        return response()->json(['mentees' => Alpha::retrieveMentees($excludeBonks, $year, $haveTraining)]);
    }

    /**
     * Retrieve all the Alphas in the current year
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function alphas(): JsonResponse
    {
        $this->authorize('isMentor');
        return response()->json(['alphas' => Alpha::retrieveAllAlphas()]);
    }

    /**
     * Find all the current year alpha shift and who is signed up (if any)
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function alphaSchedule(): JsonResponse
    {
        $this->authorize('isMentor');
        $year = $this->getYear();

        return response()->json(['slots' => Alpha::retrieveAlphaScheduleForYear($year)]);
    }

    /**
     * Find the current mentors and if they are on duty.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function mentors(): JsonResponse
    {
        $this->authorize('isMentor');
        return response()->json(['mentors' => Alpha::retrieveMentors()]);
    }

    /**
     * Assign or update the mentors a person
     *
     * @throws AuthorizationException
     */

    public function mentorAssignment(): JsonResponse
    {
        $this->authorize('isMentor');

        $params = request()->validate([
            'assignments.*.person_id' => 'required|integer',
            'assignments.*.status' => [
                'required',
                'string',
                Rule::in([PersonMentor::PASS, PersonMentor::PENDING, PersonMentor::BONK])
            ],
            'assignments.*.mentor_ids' => 'present|array',
            'assignments.*.mentor_ids.*' => 'present|integer|exists:person,id'
        ]);

        $alphas = $params['assignments'];

        $ids = array_column($alphas, 'person_id');
        $year = current_year();

        $people = Person::findOrFail($ids)->keyBy('id');
        $allMentors = PersonMentor::whereIntegerInRaw('person_id', $ids)->where('mentor_year', $year)->get()->groupBy('person_id');

        $results = [];
        foreach ($alphas as $alpha) {
            $personId = $alpha['person_id'];
            $person = $people[$personId];
            $status = $alpha['status'];
            $desiredMentorIds = $alpha['mentor_ids'];

            if (isset($allMentors[$personId])) {
                $currentMentors = $allMentors[$personId]->pluck('mentor_id')->toArray();
            } else {
                $currentMentors = [];
            }

            $mentorCount = 0;
            foreach ($desiredMentorIds as $mentorId) {
                if (in_array($mentorId, $currentMentors)) {
                    $mentorCount++;
                }
            }

            $mentors = [];
            $mentorIds = [];
            if ($mentorCount == count($desiredMentorIds) && $mentorCount == count($currentMentors)) {
                // Simple status update
                PersonMentor::where('person_id', $personId)->where('mentor_year', $year)->update(['status' => $status]);
                foreach ($allMentors[$personId] as $mentor) {
                    $mentorIds[] = $mentor->mentor_id;
                    $mentors[] = [
                        'person_mentor_id' => $mentor->id,
                        'mentor_id' => $mentor->mentor_id
                    ];
                }
            } else {
                // Rebuild the mentors
                PersonMentor::where('person_id', $personId)->where('mentor_year', $year)->delete();
                foreach ($alpha['mentor_ids'] as $mentorId) {
                    $mentor = PersonMentor::create([
                        'person_id' => $person->id,
                        'mentor_id' => $mentorId,
                        'status' => $status,
                        'mentor_year' => $year
                    ]);
                    $mentorIds[] = $mentor->mentor_id;
                    $mentors[] = [
                        'person_mentor_id' => $mentor->id,
                        'mentor_id' => $mentor->mentor_id
                    ];
                }
            }

            $callsigns = Person::select('id', 'callsign')->whereIntegerInRaw('id', $mentorIds)->get()->keyBy('id');
            usort($mentors, function ($a, $b) use ($callsigns) {
                return strcasecmp($callsigns[$a['mentor_id']]->callsign, $callsigns[$b['mentor_id']]->callsign);
            });

            $results[] = [
                'person_id' => $person->id,
                'mentors' => $mentors,
                'status' => $status
            ];
        }

        return response()->json(['assignments' => $results]);
    }

    /**
     * Find the alphas and what their mentor results are
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function verdicts(): JsonResponse
    {
        $this->authorize('isMentor');

        return response()->json(['alphas' => Alpha::retrieveVerdicts()]);
    }

    /**
     * Mint Shiny Pennies, Bonk Alphas for fun and profit!
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function convertAlphas(): JsonResponse
    {
        $this->authorize('isMentor');

        $params = request()->validate([
            'alphas.*.id' => 'required|integer',
            'alphas.*.status' => [
                'required',
                'string',
                Rule::in([Person::ACTIVE, Person::BONKED])
            ]
        ]);

        $alphas = $params['alphas'];

        $people = Person::findOrFail(array_column($alphas, 'id'))->keyBy('id');

        $results = [];
        foreach ($alphas as $alpha) {
            $alphaId = $alpha['id'];
            $status = $alpha['status'];

            $person = $people[$alphaId];
            if ($person->status != $status) {
                $oldStatus = $person->status;
                $person->changeStatus($status, $oldStatus, 'mentor conversion');
                $person->auditReason = 'mentor conversion';
                $person->saveWithoutValidation();
            }

            $results[] = [
                'id' => $person->id,
                'status' => $person->status
            ];
        }

        return response()->json(['alphas' => $results]);
    }

    /**
     * Find prospective accounts who are eligible to become Alphas
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function eligibleAlphas() : JsonResponse
    {
        $this->authorize('isMentor');

        return response()->json([ 'prospectives' => ProspectiveNewVolunteer::retrievePotentialAlphas() ]);
    }

    /**
     * Find prospective accounts who are eligible to become Alphas
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function convertProspectives() : JsonResponse
    {
        $this->authorize('isMentor');

        $params = request()->validate([
            'prospectives' => 'required|array',
            'prospectives.*' => 'integer'
        ]);

        return response()->json([ 'alphas' => ProspectiveNewVolunteer::convertProspectivesToAlphas($params['prospectives']) ]);
    }

}

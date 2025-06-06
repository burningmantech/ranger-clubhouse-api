<?php

namespace App\Http\Controllers;

use App\Lib\Alpha;
use App\Lib\ClubhouseCache;
use App\Lib\GroundHogDay;
use App\Lib\ProspectiveNewVolunteer;
use App\Models\Person;
use App\Models\PersonMentor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Psr\SimpleCache\InvalidArgumentException;

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
        $params = request()->validate([
            'off_duty' => 'sometimes|boolean'
        ]);

        if ($params['off_duty'] ?? false) {
            $alphas = DB::table('person')
                ->select('person.id', 'person.callsign')
                ->leftJoin('timesheet', 'timesheet.person_id', 'person.id')
                ->where('person.status', Person::ALPHA)
                ->whereNull('timesheet.on_duty')
                ->orderBy('callsign')
                ->get();
            return response()->json(['alphas' => $alphas]);
        } else {
            return response()->json(['alphas' => Alpha::retrieveAllAlphas()]);
        }
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
     * Find the current mittens and if they are on duty.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function mittens(): JsonResponse
    {
        $this->authorize('isMentor');
        return response()->json(['mittens' => Alpha::retrieveMittens()]);
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
                Rule::in([
                    PersonMentor::PASS,
                    PersonMentor::PENDING,
                    PersonMentor::BONK,
                    PersonMentor::SELF_BONK
                ])
            ],
            'assignments.*.mentor_ids' => 'present|array',
            'assignments.*.mentor_ids.*' => 'present|integer|exists:person,id',
            'year' => 'required|integer',
        ]);


        return response()->json(['assignments' => Alpha::mentorAssignments($params['year'], $params['assignments'])]);
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
                $person->status = $status;
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

    public function eligibleAlphas(): JsonResponse
    {
        $this->authorize('isMentor');

        return response()->json(['prospectives' => ProspectiveNewVolunteer::retrievePotentialAlphas()]);
    }

    /**
     * Find prospective accounts who are eligible to become Alphas
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function convertProspectives(): JsonResponse
    {
        $this->authorize('isMentor');

        $params = request()->validate([
            'prospectives' => 'required|array',
            'prospectives.*' => 'integer'
        ]);

        return response()->json(['alphas' => ProspectiveNewVolunteer::convertProspectivesToAlphas($params['prospectives'])]);
    }

    /**
     * Set up the database for Mentor training.
     *
     * @return JsonResponse
     * @throws AuthorizationException|InvalidArgumentException
     */

    public function setupTrainingData(): JsonResponse
    {
        $this->authorize('isMentor');

        if (!is_ghd_server()) {
            throw new AuthorizationException("Mentor Groundhog Day Setup is only available on the Training Server.");
        }

        if (ClubhouseCache::has('mentor-training-data')) {
            throw new AuthorizationException("Mentor Groundhog Day Setup has already been run.");
        }

        GroundHogDay::setupMentorTrainingData(config('clubhouse.GroundhogDayTime'));

        ClubhouseCache::put('mentor-training-data', true);

        return $this->success();
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;
use App\Models\Person;
use App\Models\PersonMentor;
use App\Policies\PersonMentorPolicy;

use App\Lib\Alpha;

class MentorController extends ApiController
{
    /*
     * Retrieve all Mentees for a given year
     */

    public function mentees()
    {
        $this->authorize('mentees', [ PersonMentor::class ]);

        $year = $this->getYear();

        return response()->json([ 'mentees' => PersonMentor::findMenteesForYear($year, $this->userCanViewEmail()) ]);
    }

    /*
     * Retrieve all potential alphas
     *
     * exclude_bonks: do no include people who have already been bonked
     * exclude_photos: do not attempt to obtain the photo/lambase status
     */


    public function potentials()
    {
        $params = request()->validate([
            'exclude_bonks'     => 'sometimes|boolean',
            'exclude_photos' => 'sometimes|boolean',
        ]);

        $excludeBonks = $params['exclude_bonks'] ?? false;
        $excludePhotos = $params['exclude_photos'] ?? false;

        return response()->json(['potentials' => Alpha::retrievePotentials($excludeBonks, $excludePhotos)]);
    }

    /*
     * Update the mentors_{flag,flag_note,notes} columns for potential alphas
     */

    public function updatePotentials()
    {
        $params = request()->validate([
            'potentials.*.id' => 'required|integer',
            'potentials.*.mentors_flag' => 'required|boolean',
            'potentials.*.mentors_flag_note' => 'present|string|nullable',
            'potentials.*.mentors_notes' => 'present|string|nullable'
        ]);

        $potentials = $params['potentials'];
        $ids = array_column($potentials, 'id');

        $people = Person::findOrFail($ids)->keyBy('id');

        foreach ($potentials as $potential) {
            $person = $people[$potential['id']] ?? null;
            if (!$person) {
                continue;
            }

            $person->mentors_flag = $potential['mentors_flag'];
            $person->mentors_flag_note = $potential['mentors_flag_note'] ?? '';
            $person->mentors_notes = $potential['mentors_notes'] ?? '';
            $changes = $person->getChangedValues();

            if (!empty($changes)) {
                // Track changes
                $person->saveWithoutValidation();
                $this->log('person-update', 'mentor update', $changes, $person->id);
            }
        }

        return $this->success();
    }

    /*
     * Find all the people who are status alpha
     */

    public function alphas()
    {
        return response()->json([ 'alphas' => Alpha::findAllAlphas() ]);
    }

    /*
     * Find all the current year alpha shift and who is signed up (if any)
     */

    public function alphaSchedule()
    {
        $year = $this->getYear();

        return response()->json([ 'slots' => Alpha::retrieveAlphaScheduleForYear($year) ]);
    }

    /*
     * Find the current mentors and if they are on duty.
     */

    public function mentors()
    {
        return response()->json([ 'mentors' => Alpha::retrieveMentors() ]);
    }

    /*
     * Assign or update the mentors a person
     */

    public function mentorAssignment()
    {
        $params = request()->validate([
             'assignments.*.person_id' => 'required|integer',
             'assignments.*.status' => 'required|string',
             'assignments.*.mentors.*.mentor_id' => 'present|integer|exists:person,id',
         ]);

        $alphas = $params['assignments'];

        $result = [];

        $ids = array_column($alphas, 'person_id');
        $year = current_year();

        $people = Person::findOrFail($ids)->keyBy('id');
        $allMentors = PersonMentor::whereIn('person_id', $ids)->where('mentor_year', $year)->get()->groupBy('person_id');

        $mentorCache = [];
        foreach ($alphas as $alpha) {
            $personId = $alpha['person_id'];
            $person = $people[$personId];
            $status = $alpha['status'];

            if (isset($allMentors[$personId])) {
                $currentMentors = $allMentors[$personId]->pluck('mentor_id')->toArray();
            } else {
                $currentMentors = [];
            }

            $mentorCount = 0;
            foreach ($alpha['mentors'] as $mentor) {
                $mentorId = $mentor['mentor_id'];
                if (in_array($mentorId, $currentMentors)) {
                    $mentorCount++;
                }
            }

            $mentors = [];
            $mentorIds = [];
            if ($mentorCount > 0 && $mentorCount == count($currentMentors)) {
                // Simple status update
                PersonMentor::where('person_id', $personId)->where('mentor_year', $year)->update([ 'status' => $status ]);
                foreach ($allMentors[$personId] as $mentor) {
                    $mentorIds[] = $mentor->mentor_id;
                    $mentors[] = [
                        'person_mentor_id' => $mentor->id,
                        'mentor_id'        => $mentor->mentor_id
                    ];
                }
            } else {
                // Rebuild the mentors
                PersonMentor::where('person_id', $personId)->where('mentor_year', $year)->delete();
                foreach ($alpha['mentors'] as $mentor) {
                    $mentorId = $mentor['mentor_id'];
                    $mentor = PersonMentor::create([
                         'person_id'   => $person->id,
                         'mentor_id'   => $mentorId,
                         'status'      => $status,
                         'mentor_year' => $year
                    ]);
                    $mentorIds[] = $mentor->mentor_id;
                    $mentors[] = [
                         'person_mentor_id' => $mentor->id,
                         'mentor_id'        => $mentor->mentor_id
                     ];
                }
            }

            $callsigns = Person::select('id', 'callsign')->whereIn('id', $mentorIds)->get()->keyBy('id');
            usort($mentors, function ($a, $b) use ($callsigns) {
                return strcasecmp($callsigns[$a['mentor_id']]->callsign, $callsigns[$b['mentor_id']]->callsign);
            });

            $results[] = [
                 'person_id' => $person->id,
                 'mentors'   => $mentors,
                 'status'    => $status
             ];
        }

        return response()->json([ 'assignments' => $results ]);
    }

    /*
     * Find the alphas and what their mentor results are
     */

    public function verdicts()
    {
        return response()->json([ 'alphas' => Alpha::retrieveVerdicts() ]);
    }

    /*
     *  Mint Shiny Pennies, Bonk Alphas for fun and profit!
     */

    public function convert()
    {
        $params = request()->validate([
            'alphas.*.id'     => 'required|integer',
            'alphas.*.status' => 'required|string'
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
                $person->saveWithoutValidation();
                $this->log('person-update', 'mentor update', [ 'status' => [ 'alpha', $status ]], $person->id);
                $person->changeStatus($status, 'alpha', 'mentor update');
            }

            $results[] = [
                'id'    => $person->id,
                'status' => $person->status
            ];
        }

        return response()->json([ 'alphas' => $results ]);
    }
}

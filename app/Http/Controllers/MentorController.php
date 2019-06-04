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

        return response()->json([ 'mentees' => PersonMentor::findMenteesForYear($year) ]);
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
             'assignments.*.mentors.*.person_mentor_id' => 'sometimes|integer|exists:person_mentor,id',
             'assignments.*.mentors.*.mentor_id' => 'present|integer',
         ]);

        $alphas = $params['assignments'];

        $result = [];

        $ids = array_column($alphas, 'person_id');

        $people = Person::findOrFail($ids)->keyBy('id');

        foreach ($alphas as $alpha) {
            $person = $people[$alpha['person_id']];
            $status = $alpha['status'];

            $mentors = [];
            foreach ($alpha['mentors'] as $mentor) {
                $mentorId = $mentor['mentor_id'];
                if (isset($mentor['person_mentor_id'])) {
                    $pm = PersonMentor::find($mentor['person_mentor_id']);
                    if ($pm->person_id != $person->id) {
                        throw new \InvalidArgumentException("person_mentor#{$pm->id} does not belong to person#{$person->id}");
                    }
                    $pm->status = $status;
                    $pm->mentor_id = $mentorId;
                    $pm->save();
                } else {
                    $pm = PersonMentor::create([
                         'person_id'   => $person->id,
                         'mentor_id'   => $mentor['mentor_id'],
                         'status'      => $status,
                         'mentor_year' => date('Y')
                     ]);
                }

                $mentors[] = [
                     'person_mentor_id' => $pm->id,
                     'mentor_id'        => $pm->mentor_id
                 ];
            }

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

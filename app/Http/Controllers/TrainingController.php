<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\Training;

class TrainingController extends ApiController
{
    /*
     * Show a training position
     */

    public function show($id)
    {
        $this->authorize('show', [ Training::class ]);

        $training = Training::findOrFail($id);

        return response()->json($training);
    }

    /*
     * Show all people who have mulitple rollments for a given year
     */

    public function multipleEnrollmentsReport($id)
    {
        list ($training, $year) = $this->getTrainingAndYear($id);

        return response()->json([
            'enrollments' => $training->retrieveMultipleEnrollments($year),
            'year'        => $year
        ]);
    }

    /*
     * Show how full each training session is
     */

    public function capacityReport($id)
    {
        list ($training, $year) = $this->getTrainingAndYear($id);

        return response()->json([
            'slots' => $training->retrieveSlotsCapacity($year)
        ]);
    }

    /*
     * Show who has completed the training for a given year
     */

    public function peopleTrainingCompleted($id)
    {
        list ($training, $year) = $this->getTrainingAndYear($id);

        return response()->json([
            'slots' => $training->retrievePeopleForTrainingCompleted($year)
        ]);
    }

    /*
     * Show who has not completed training or not signed up for training
     * (ART modules only)
     */

    public function untrainedPeopleReport($id)
    {
        list ($training, $year) = $this->getTrainingAndYear($id);

        return response()->json($training->retrieveUntrainedPeople($year));
    }

    /*
     * Obtain the training position, and year requested
     */

    private function getTrainingAndYear($id) {
        $this->authorize('show', [ Training::class ]);

        $params = request()->validate([
            'year'  => 'required|integer'
        ]);

        $training = Training::findOrFail($id);

        return [ $training, $params['year'] ];
    }
}

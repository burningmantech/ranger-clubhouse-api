<?php

namespace App\Http\Controllers;

use App\Exceptions\UnacceptableConditionException;
use App\Lib\Reports\TrainedNoWorkReport;
use App\Lib\Reports\TrainerAttendanceReport;
use App\Lib\Reports\TrainingCompletedReport;
use App\Lib\Reports\TrainingMultipleEnrollmentReport;
use App\Lib\Reports\TrainingSlotCapacityReport;
use App\Lib\Reports\TrainingUntrainedPeopleReport;
use App\Models\Training;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class TrainingController extends ApiController
{
    /**
     * Show a training position
     * @param $id
     * @return JsonResponse
     * @throws AuthorizationException|UnacceptableConditionException
     */

    public function show($id): JsonResponse
    {
        $training = Training::findOrFail($id);
        $this->authorize('show', $training);
        return response()->json($training);
    }

    /**
     * Show all people who have multiple enrollments for a given year
     *
     * @param $id
     * @return JsonResponse
     * @throws AuthorizationException|UnacceptableConditionException
     */

    public function multipleEnrollmentsReport($id): JsonResponse
    {
        list($training, $year) = $this->getTrainingAndYear($id);

        return response()->json([
            'enrollments' => TrainingMultipleEnrollmentReport::execute($training, $year),
            'year' => $year
        ]);
    }

    /**
     * Show how full each training session is
     *
     * @param $id
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function capacityReport($id): JsonResponse
    {
        list($training, $year) = $this->getTrainingAndYear($id);

        return response()->json(TrainingSlotCapacityReport::execute($training, $year));
    }

    /**
     * Show who has completed the training for a given year
     *
     * @param $id
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function peopleTrainingCompleted($id): JsonResponse
    {
        list($training, $year) = $this->getTrainingAndYear($id);

        return response()->json([
            'slots' => TrainingCompletedReport::execute($training, $year)
        ]);
    }

    /**
     * Show who has not completed training or not signed up for training
     * (ART modules only)
     * @param $id
     * @return JsonResponse
     * @throws AuthorizationException|UnacceptableConditionException
     */

    public function untrainedPeopleReport($id): JsonResponse
    {
        list($training, $year) = $this->getTrainingAndYear($id);
        return response()->json(TrainingUntrainedPeopleReport::execute($training, $year));
    }

    /**
     * Report on the people who trained yet did not work.
     *
     * @param $id
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws UnacceptableConditionException
     */

    public function trainedNoWorkReport($id): JsonResponse
    {
        list($training, $year) = $this->getTrainingAndYear($id);

        return response()->json(TrainedNoWorkReport::execute($training->id, $year));
    }

    /**
     * Show who has not completed training or not signed up for training
     * (ART modules only)
     */

    public function trainerAttendanceReport($id): JsonResponse
    {
        list($training, $year) = $this->getTrainingAndYear($id);
        return response()->json(['trainers' => TrainerAttendanceReport::execute($training, $year)]);
    }

    /**
     * Get the training position, and year requested
     *
     * @param $id
     * @return array
     * @throws AuthorizationException|UnacceptableConditionException
     */

    private function getTrainingAndYear($id): array
    {
        $year = $this->getYear();
        $training = Training::findOrFail($id);
        $this->authorize('show', $training);

        return [$training, $year];
    }

}

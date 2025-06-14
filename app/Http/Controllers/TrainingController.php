<?php

namespace App\Http\Controllers;

use App\Exceptions\UnacceptableConditionException;
use App\Lib\Reports\ARTMenteesReport;
use App\Lib\Reports\TrainedNoWorkReport;
use App\Lib\Reports\TrainerAttendanceReport;
use App\Lib\Reports\TrainingCompletedReport;
use App\Lib\Reports\TrainingMultipleEnrollmentReport;
use App\Lib\Reports\TrainingNotesReport;
use App\Lib\Reports\TrainingSlotCapacityReport;
use App\Lib\Reports\TrainingUntrainedPeopleReport;
use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\Position;
use App\Models\Training;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class TrainingController extends ApiController
{
    /**
     * Show a training position
     *
     * @param Training $training
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(Training $training): JsonResponse
    {
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

    public function multipleEnrollmentsReport(Training $training): JsonResponse
    {
        $this->authorize('multipleEnrollmentsReport', $training);
        $year = $this->getYear();

        return response()->json([
            'enrollments' => TrainingMultipleEnrollmentReport::execute($training, $year),
            'year' => $year
        ]);
    }

    /**
     * Show how full each training session is
     *
     * @param Training $training
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function capacityReport(Training $training): JsonResponse
    {
        $this->authorize('capacityReport', $training);
        $year = $this->getYear();

        return response()->json(TrainingSlotCapacityReport::execute($training, $year));
    }

    /**
     * Show who has completed the training for a given year
     *
     * @param Training $training
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function peopleTrainingCompleted(Training $training): JsonResponse
    {
        $this->authorize('peopleTrainingCompleted', $training);
        $year = $this->getYear();

        return response()->json([
            'slots' => TrainingCompletedReport::execute($training, $year)
        ]);
    }

    /**
     * Show who has not completed training or not signed up for training
     * (ART modules only)
     *
     * @param Training $training
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function untrainedPeopleReport(Training $training): JsonResponse
    {
        $this->authorize('untrainedPeopleReport', $training);

        $year = $this->getYear();

        return response()->json(TrainingUntrainedPeopleReport::execute($training, $year));
    }

    /**
     * Report on the people who trained yet did not work.
     *
     * @param Training $training
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function trainedNoWorkReport(Training $training): JsonResponse
    {
        $this->authorize('trainedNoWorkReport', $training);
        $year = $this->getYear();
        return response()->json(TrainedNoWorkReport::execute($training->id, $year));
    }

    /**
     * Show who has not completed training or not signed up for training
     * (ART modules only)
     *
     * @param Training $training
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function trainerAttendanceReport(Training $training): JsonResponse
    {
        $this->authorize('trainerAttendanceReport', $training);
        $year = $this->getYear();

        return response()->json(['trainers' => TrainerAttendanceReport::execute($training, $year)]);
    }

    /**
     * Report on the current mentees
     * @param Training $training
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function mentees(Training $training): JsonResponse
    {
        $this->authorize('mentees', $training);
        return response()->json(ARTMenteesReport::execute($training));
    }

    /**
     * Revoke mentee positions for mentee
     *
     * @param Training $training
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws UnacceptableConditionException
     */

    public function revokeMenteePositions(Training $training, Person $person): JsonResponse
    {
        $this->authorize('revokeMenteePositions', $training);

        $info = Position::ART_GRADUATE_TO_POSITIONS[$training->id] ?? null;

        if (!isset($info['has_mentees'])) {
            throw new UnacceptableConditionException("ART does not have any mentee positions");
        }

        $positionIds = $info['positions'];
        PersonPosition::removeIdsFromPerson($person->id, $positionIds, "ART mentee position revoke");

        return $this->success();
    }

    /**
     * Collect an event's training notes into one report
     *
     * @param Training $training
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function trainingNotes(Training $training): JsonResponse
    {
        $this->authorize('trainingNotes', $training);
        $year = $this->getYear();

        return response()->json([
            'people' => TrainingNotesReport::execute($training->id, $year)
        ]);
    }
}

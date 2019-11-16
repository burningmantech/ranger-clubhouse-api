<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;

use App\Models\Person;
use App\Models\Schedule;
use App\Models\TraineeStatus;
use App\Models\TrainerStatus;
use App\Models\Training;
use App\Models\TrainingSession;


class TrainingSessionController extends ApiController
{

    /*
     * Retrieve the session, students and teachers for a given session.
     */

    public function show($id)
    {
        $session = TrainingSession::findOrFail($id);
        $this->authorize('show', $session);

        return response()->json(
            [
            'slot'  => $session,
            'students' => $session->retrieveStudents(),
            'trainers' => $session->retrieveTrainers(),
            ]
        );
    }
    /*
     * Retrieve all the training sessions for a given training.
     */

    public function sessions()
    {
        $params = request()->validate([
            'training_id'   => 'required|integer',
            'year'          => 'required|integer',
        ]);

        $training = Training::findOrFail($params['training_id']);
        $this->authorize('show', $training);

        $sessions = TrainingSession::findAllForTrainingYear($params['training_id'], $params['year']);


        $info = $sessions->map(
            function ($session) {
                return [
                'slot'  => $session,
                'trainers' => $session->retrieveTrainers(),
                ];
            }
        );

        return response()->json([ 'sessions' => $info ]);
    }

    /*
     * Score one or more individuals for a training
     */

    public function score($id)
    {
        $session = TrainingSession::findOrFail($id);
        $this->authorize('score', $session);

        $params = request()->validate([
            'students.*.id'     => 'required|integer',
            'students.*.rank'   => 'nullable|integer',
            'students.*.notes'  => 'nullable|string',
            'students.*.passed' => 'boolean',
        ]);

        $students = $params['students'];

        foreach ($params['students'] as $student) {
            $personId = $student['id'];

            $traineeStatus = TraineeStatus::firstOrNewForSession($personId, $session->id);
            $traineeStatus->fill($student);
            $changes = $traineeStatus->getChangedValues();
            $traineeStatus->save();
            if (!empty($changes)) {
                $changes['slot_id'] = $id;
                $this->log('trainee-status-update', '', $changes, $personId);
            }
        }

        return response()->json([ 'students' => $session->retrieveStudents()]);
    }

    /*
     * Mark trainers as attended, or not.
     */

     public function trainerStatus($id)
     {
         $session = TrainingSession::findOrFail($id);
         $this->authorize('trainerStatus', $session);

         $params = request()->validate([
             'trainers.*.id'     => 'required|integer',
             'trainers.*.trainer_slot_id' => 'required|integer',
             'trainers.*.status' => 'nullable|string',
         ]);

         foreach ($params['trainers'] as $trainer) {
             $personId = $trainer['id'];

             $trainerStatus = TrainerStatus::firstOrNewForSession($session->id, $personId);
             $trainerStatus->status = $trainer['status'];
             $trainerStatus->trainer_slot_id = $trainer['trainer_slot_id'];
             $changes = $trainerStatus->getChangedValues();
             $trainerStatus->save();
             if (!empty($changes)) {
                 $changes['slot_id'] = $id;
                 $changes['trainer_slot_id'] = $trainer['trainer_slot_id'];
                 $this->log('trainer-status-update', '', $changes, $personId);
             }
         }

         return response()->json([ 'trainers' => $session->retrieveTrainers()]);
     }
}

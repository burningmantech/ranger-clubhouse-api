<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;
use App\Models\PersonIntakeNote;
use Illuminate\Http\Request;

use App\Models\PersonSlot;
use App\Models\TraineeStatus;
use App\Models\TrainerStatus;
use App\Models\Training;
use App\Models\TrainingSession;
use App\Models\TraineeNote;


class TrainingSessionController extends ApiController
{
    /*
     * Retrieve the session, students and teachers for a given session.
     */

    public function show($id)
    {
        $session = TrainingSession::findOrFail($id);
        $this->authorize('show', $session);

        return response()->json([
            'slot' => $session,
            'students' => $session->retrieveStudents(),
            'trainers' => $session->retrieveTrainers(),
        ]);
    }

    /*
     * Retrieve all the training sessions for a given training.
     */

    public function sessions()
    {
        $params = request()->validate([
            'training_id' => 'required|integer',
            'year' => 'required|integer',
        ]);

        $training = Training::findOrFail($params['training_id']);
        $this->authorize('show', $training);

        $sessions = TrainingSession::findAllForTrainingYear($params['training_id'], $params['year']);


        $info = $sessions->map(
            function ($session) {
                return [
                    'slot' => $session,
                    'trainers' => $session->retrieveTrainers(),
                ];
            }
        );

        return response()->json(['sessions' => $info]);
    }

    /*
     * Score a student
     */

    public function scoreStudent($slotId)
    {
        $session = TrainingSession::findOrFail($slotId);
        $this->authorize('score', $session);

        $params = request()->validate([
            'id' => 'required|integer',
            'rank' => 'nullable|integer',
            'note' => 'nullable|string',
            'passed' => 'boolean',
            'feedback_delivered' => 'sometimes|boolean'
        ]);

        $personId = $params['id'];

        if (!PersonSlot::haveSlot($personId, $slotId)) {
            return $this->restError('Person is not signed up for the slot');
        }

        $traineeStatus = TraineeStatus::firstOrNewForSession($personId, $slotId);
        $traineeStatus->rank = $params['rank'];
        if (!$session->isArt() && isset($params['feedback_delivered'])) {
            $traineeStatus->feedback_delivered = $params['feedback_delivered'];

        }
        $traineeStatus->passed = $params['passed'];

        if ($traineeStatus->isDirty('rank')) {
            $rankUpdated = true;
            $oldRank = $traineeStatus->getOriginal('rank');
        } else {
            $rankUpdated = false;
        }

        if (!$traineeStatus->save()) {
            return $this->restError($traineeStatus);
        }

        if ($rankUpdated) {
            TraineeNote::record($personId, $session->id, "rank change [" . ($oldRank ?? 'no rank') . "] -> [" . ($traineeStatus->rank ?? 'no rank') . "]", true);
        }

        $note = trim($params['note'] ?? '');
        if (!empty($note)) {
            TraineeNote::record($personId, $session->id, $note);
        }

        return response()->json(['students' => $session->retrieveStudents()]);
    }

    /*
     * Mark trainers as attended, or not.
     */

    public function trainerStatus($id)
    {
        $session = TrainingSession::findOrFail($id);
        $this->authorize('trainerStatus', $session);

        $params = request()->validate([
            'trainers.*.id' => 'required|integer',
            'trainers.*.trainer_slot_id' => 'required|integer',
            'trainers.*.status' => 'nullable|string',
        ]);

        foreach ($params['trainers'] as $trainer) {
            $personId = $trainer['id'];

            $trainerStatus = TrainerStatus::firstOrNewForSession($session->id, $personId);
            $trainerStatus->status = $trainer['status'];
            $trainerStatus->trainer_slot_id = $trainer['trainer_slot_id'];
            $trainerStatus->save();
        }

        return response()->json(['trainers' => $session->retrieveTrainers()]);
    }

    /**
     * Retrieve the trainers
     */

    public function trainers($id)
    {
        $session = TrainingSession::findOrFail($id);
        $trainerGroups = $session->retrieveTrainers();

        $trainers = [];
        foreach ($trainerGroups as $group) {
            foreach ($group['trainers'] as $trainer) {
                $trainers[] = [
                    'id' => $trainer['id'],
                    'callsign' => $trainer['callsign']
                ];
            }
        }

        usort($trainers, function ($a, $b) {
            return strcasecmp($a['callsign'], $b['callsign']);
        });

        return response()->json(['trainers' => $trainers]);
    }
}

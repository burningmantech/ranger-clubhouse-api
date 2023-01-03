<?php

namespace App\Http\Controllers;

use App\Models\PersonPosition;
use App\Models\PersonSlot;
use App\Models\TraineeNote;
use App\Models\TraineeStatus;
use App\Models\TrainerStatus;
use App\Models\Training;
use App\Models\TrainingSession;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class TrainingSessionController extends ApiController
{
    /**
     * Retrieve the session, students and teachers for a given session.
     *
     * @param TrainingSession $training_session
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(TrainingSession $training_session): JsonResponse
    {
        $this->authorize('show', $training_session);

        return response()->json([
            'slot' => $training_session,
            'students' => $training_session->retrieveStudents(),
            'trainers' => $training_session->retrieveTrainers(),
            'team_names' => $training_session->retrieveTeamNameLegend(),
        ]);
    }

    /**
     *  Retrieve all the training sessions for a given training (position) & year.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function sessions(): JsonResponse
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

    /**
     * Score a student
     *
     * @param TrainingSession $training_session
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function scoreStudent(TrainingSession $training_session): JsonResponse
    {
        $this->authorize('score', $training_session);

        $params = request()->validate([
            'id' => 'required|integer',
            'rank' => 'nullable|integer',
            'note' => 'nullable|string',
            'passed' => 'boolean',
            'feedback_delivered' => 'sometimes|boolean'
        ]);

        $personId = $params['id'];

        $slotId = $training_session->id;

        if (!PersonSlot::haveSlot($personId, $slotId)) {
            return $this->restError('Person is not signed up for the slot');
        }

        $traineeStatus = TraineeStatus::firstOrNewForSession($personId, $slotId);
        $traineeStatus->rank = $params['rank'];
        if (!$training_session->isArt() && isset($params['feedback_delivered'])) {
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
            TraineeNote::record($personId, $slotId, "rank change [" . ($oldRank ?? 'no rank') . "] -> [" . ($traineeStatus->rank ?? 'no rank') . "]", true);
        }

        $note = trim($params['note'] ?? '');
        if (!empty($note)) {
            TraineeNote::record($personId, $slotId, $note);
        }

        return response()->json(['students' => $training_session->retrieveStudents()]);
    }

    /**
     * Mark trainers as attended, or not.
     *
     * @param TrainingSession $training_session
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function trainerStatus(TrainingSession $training_session): JsonResponse
    {
        $this->authorize('trainerStatus', $training_session);

        $params = request()->validate([
            'trainers.*.id' => 'required|integer',
            'trainers.*.trainer_slot_id' => 'required|integer',
            'trainers.*.status' => 'nullable|string',
        ]);

        foreach ($params['trainers'] as $trainer) {
            $personId = $trainer['id'];

            $trainerStatus = TrainerStatus::firstOrNewForSession($training_session->id, $personId);
            $trainerStatus->status = $trainer['status'];
            $trainerStatus->trainer_slot_id = $trainer['trainer_slot_id'];
            $trainerStatus->save();
        }

        return response()->json(['trainers' => $training_session->retrieveTrainers()]);
    }

    /**
     * Find the trainers for a given session.
     *
     * @param TrainingSession $training_session
     * @return JsonResponse
     */

    public function trainers(TrainingSession $training_session): JsonResponse
    {
        $trainerGroups = $training_session->retrieveTrainers();

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

    /**
     * Update a trainee note. Only allowed by the note's creator.
     *
     * @param TraineeNote $trainee_note
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function updateNote(TraineeNote $trainee_note): JsonResponse
    {
        if ($trainee_note->person_source_id != $this->user->id || $trainee_note->is_log) {
            $this->notPermitted('Not authorized to update note.');
        }

        $params = request()->validate(['note' => 'required|string']);

        $trainee_note->note = $params['note'];
        $trainee_note->saveOrThrow();
        return $this->success();
    }

    /**
     * Delete a trainee note. Only allowed by the note's creator.
     *
     * @param TraineeNote $trainee_note
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function deleteNote(TraineeNote $trainee_note): JsonResponse
    {
        if ($trainee_note->person_source_id != $this->user->id || $trainee_note->is_log) {
            $this->notPermitted('Not authorized to delete note.');
        }
        $trainee_note->delete();
        return $this->restDeleteSuccess();
    }

    /**
     * Retrieve graduation candidates
     *
     * @param TrainingSession $training_session
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function graduationCandidates(TrainingSession $training_session): JsonResponse
    {
        $this->authorize('graduationCandidates', $training_session);

        $result = $training_session->graduationCandidates();
        if (!$result) {
            return response()->json(['status' => 'no-positions ']);
        }

        return response()->json($result);
    }

    /**
     * Retrieve graduation candidates
     *
     * @param TrainingSession $training_session
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function graduateCandidates(TrainingSession $training_session): JsonResponse
    {
        $this->authorize('graduateCandidates', $training_session);

        $candidates = $training_session->graduationCandidates();
        if (!$candidates) {
            throw new InvalidArgumentException('ART has no positions to graduate to.');
        }

        $params = request()->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer'
        ]);

        $ids = $params['ids'];
        $people = $candidates['people'];
        $peopleById = [];
        foreach ($people as $candidate) {
            $peopleById[$candidate['id']] = $candidate;
        }

        $results = [];
        $positionIds = array_map(fn($p) => $p['id'], $candidates['positions']);

        foreach ($ids as $id) {
            $candidate = $peopleById[$id] ?? null;
            if (!$candidate) {
                $results[] = [
                    'id' => $id,
                    'status' => 'not-found'
                ];
                continue;
            }

            $status = $candidate['status'];
            if ($status !== 'candidate') {
                $results[] = [
                    'id' => $id,
                    'status' => $status
                ];
            }

            PersonPosition::addIdsToPerson($id, $positionIds, 'granted via graduate trainee');
            $results[] = [
                'id' => $id,
                'status' => 'success'
            ];
        }

        return response()->json(['people' => $results]);
    }

}

<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

use App\Lib\Intake;

use App\Models\Role;
use App\Models\Person;
use App\Models\PersonIntake;
use App\Models\PersonIntakeNote;

use App\Http\Controllers\ApiController;


class IntakeController extends ApiController
{
    /**
     * Retrieve all the PNVs and their intake history for a given year
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function index()
    {
        $this->authorize('isIntake');
        $year = $this->getYear();

        return response()->json(['people' => Intake::retrieveAllForYear($year)]);
    }

    /**
     * Retrieve the intake spigot - a collection of progression counts broken down by day.
     * (photo uploaded, training signups, etc.)
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function spigot()
    {
        $this->authorize('isIntake');

        return response()->json(['days' => Intake::retrieveSpigotFlowForYear($this->getYear())]);
    }

    /**
     * Retrieve the PNV intake history for a given year.
     * (similar to IntakeController::index but for a specific person)
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function history(Person $person)
    {
        $this->authorize('isIntake');
        $year = $this->getYear();

        return response()->json(['person' => Intake::retrieveIdsForYear([$person->id], $year, false)[0]]);
    }

    /**
     * Append a note and/or set a ranking for a type & year
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function appendNote(Person $person)
    {
        $params = request()->validate([
            'year' => 'required|integer',
            'type' => [
                'required',
                Rule::in(['rrn', 'vc', 'mentor', 'personnel'])
            ],
            'note' => 'sometimes|string',
            'ranking' => 'sometimes|integer|nullable'
        ]);

        $type = $params['type'];
        switch ($type) {
            case 'vc':
                $role = Role::VC;
                break;
            case 'mentor':
                $role = Role::MENTOR;
                break;
            case 'personnel':
                $role = Role::ADMIN;
                break;
            default:
                $role = Role::INTAKE;
                break;

        }

        if (!$this->userHasRole($role)) {
            $this->notPermitted("Not authorized");
        }

        $year = $params['year'];
        $personId = $person->id;

        $note = $params['note'] ?? null;

        if ($note) {
            PersonIntakeNote::record($personId, $year, $type, $note);
        }

        if (isset($params['ranking'])) {
            $rankAttr = $type . "_rank";
            $intake = PersonIntake::findForPersonYearOrNew($personId, $year);
            $intake->{$rankAttr} = $params['ranking'];
            if ($intake->isDirty($rankAttr)) {
                $rankUpdated = true;
                $oldRank = $intake->getOriginal($rankAttr);
            } else {
                $rankUpdated = false;
            }

            if (!$intake->save()) {
                return $this->restError($intake);
            }

            if ($rankUpdated) {
                PersonIntakeNote::record($personId, $year, $type,
                    "rank change [" . ($oldRank ?? 'no rank') . "] -> [" . ($intake->{$rankAttr} ?? 'no rank') . "]", true);
            }
        }

        return $this->success();
    }

    /**
     * Update the on an intake note. Only allowed by the note's creator.
     *
     * @param PersonIntakeNote $note
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function updateNote(PersonIntakeNote $person_intake_note)
    {
        if ($person_intake_note->person_source_id != $this->user->id) {
            $this->notPermitted('Not authorized to update note.');
        }

        $params = request()->validate(['note' => 'required|string']);

        $person_intake_note->note = $params['note'];
        $person_intake_note->saveOrThrow();
        return $this->success();
    }

    /**
     * Delete an intake note. Only allowed by the note's creator.
     *
     * @param PersonIntakeNote $note
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function deleteNote(PersonIntakeNote $person_intake_note)
    {
        if ($person_intake_note->person_source_id != $this->user->id) {
            $this->notPermitted('Not authorized to delete note.');
        }
        $person_intake_note->delete();
        return $this->restDeleteSuccess();
    }
}

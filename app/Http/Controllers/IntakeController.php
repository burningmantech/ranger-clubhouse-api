<?php

namespace App\Http\Controllers;

use App\Lib\Intake;
use App\Lib\Reports\ShinyPennyReport;
use App\Mail\WelcomeMail;
use App\Models\Person;
use App\Models\PersonIntake;
use App\Models\PersonIntakeNote;
use App\Models\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IntakeController extends ApiController
{
    /**
     * Retrieve all the PNVs and their intake history for a given year
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
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

    public function spigot(): JsonResponse
    {
        $this->authorize('isVC');

        return response()->json(['days' => Intake::retrieveSpigotFlowForYear($this->getYear())]);
    }

    /**
     * Retrieve the PNV intake history for a given year.
     * (similar to IntakeController::index but for a specific person)
     *
     * @param Person $person
     * @return JsonResponse
     */

    public function history(Person $person): JsonResponse
    {
        Gate::allowIf(fn(Person $user) => $user->hasRole([Role::INTAKE, Role::REGIONAL_MANAGEMENT]));

        $year = $this->getYear();

        if (!$this->userHasRole(Role::INTAKE) && $this->userHasRole(Role::REGIONAL_MANAGEMENT)) {
            $intakeId = Auth::id();
        } else {
            $intakeId = null;
        }

        return response()->json([
            'person' => Intake::retrieveIdsForYear([$person->id], $year, false, $intakeId)[0]
        ]);
    }

    /**
     * Append a note and/or set a ranking for a type & year
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function appendNote(Person $person): JsonResponse
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
        $year = $params['year'];
        $personId = $person->id;
        $note = $params['note'] ?? null;

        $this->checkIntakePermissions($type);

        if ($note) {
            PersonIntakeNote::record($personId, $year, $type, $note);
        }

        $rankAttr = $type . "_rank";
        $intake = PersonIntake::findForPersonYearOrNew($personId, $year);
        if (isset($params['ranking']) && $params['ranking'] != $intake->{$rankAttr}) {
            $intake->{$rankAttr} = $params['ranking'];
            $oldRank = $intake->getOriginal($rankAttr);

            if (!$intake->save()) {
                return $this->restError($intake);
            }

            PersonIntakeNote::record($personId, $year, $type,
                "rank change [" . ($oldRank > 0 ? $oldRank : 'no rank') . "] -> [" . ($intake->{$rankAttr} ?? 'no rank') . "]", true);
        }

        return $this->success();
    }

    /**
     * Update the on an intake note.
     *
     * @param PersonIntakeNote $person_intake_note
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function updateNote(PersonIntakeNote $person_intake_note): JsonResponse
    {
        if ($person_intake_note->is_log) {
            $this->notPermitted('Cannot update a log note.');
        }

        $this->checkIntakePermissions($person_intake_note->type);

        $params = request()->validate(['note' => 'required|string']);

        $person_intake_note->note = $params['note'];
        $person_intake_note->saveOrThrow();
        return $this->success();
    }

    /**
     * Delete an intake note. Only allowed by the note's creator.
     *
     * @param PersonIntakeNote $person_intake_note
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function deleteNote(PersonIntakeNote $person_intake_note): JsonResponse
    {
        $this->checkIntakePermissions($person_intake_note->type);
        if ($person_intake_note->is_log) {
            $this->notPermitted('Not authorized to delete note.');
        }
        $person_intake_note->delete();
        return $this->restDeleteSuccess();
    }

    /**
     * Resend Welcome Message to PNV
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function sendWelcomeEmail(Person $person): JsonResponse
    {
        $this->authorize('sendWelcomeEmail', $person);

        if ($person->status != Person::PROSPECTIVE) {
            return response()->json(['status' => 'not-prospective'], 401);
        }

        $inviteToken = $person->createTemporaryLoginToken(Person::PNV_INVITATION_EXPIRE);
        mail_to_person($person, new WelcomeMail($person, $inviteToken), true);

        return response()->json(['status' => 'success']);
    }

    /**
     * Check to see if the user can do a intake note thing
     *
     * @param string $type
     * @return void
     * @throws AuthorizationException
     */

    private function checkIntakePermissions(string $type): void
    {
        $role = match ($type) {
            'vc' => Role::VC,
            'mentor' => Role::MENTOR,
            'personnel' => Role::ADMIN,
            'rrn' => Role::REGIONAL_MANAGEMENT,
            default => Role::INTAKE,
        };

        if (!$this->userHasRole($role)) {
            $this->notPermitted("Not authorized");
        }
    }

    /**
     * Report on Shiny Pennies for a given year.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function shinyPennyReport(): JsonResponse
    {
        $this->authorize('isVC');
        $year = $this->getYear();

        return response()->json(['people' => ShinyPennyReport::execute($year)]);
    }
}

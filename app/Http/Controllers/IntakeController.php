<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rule;

use App\Lib\Intake;

use App\Models\Person;
use App\Models\PersonIntake;
use App\Models\PersonIntakeNote;

use App\Http\Controllers\ApiController;

use App\Models\Role;

class IntakeController extends ApiController
{
    /**
     * Retrieve all the PNVs and their intake history for a given year
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws AuthorizationException
     */
    public function index()
    {
        $this->roleCheck();
        $year = $this->getYear();

        return response()->json([ 'people' => Intake::retrieveAllForYear($year) ]);
    }

    /**
     * Volu
     */
    public function spigot()
    {
        $this->roleCheck();

        return response()->json([ 'days' => Intake::retrieveSpigotFlowForYear($this->getYear()) ]);
    }

    /**
     * Retrieve the PNV intake history for a given year.
     * (similar to IntakeController::index but for a specific person)
     *
     * @param Person $person
     * @return \Illuminate\Http\JsonResponse
     * @throws AuthorizationException
     */

    public function history(Person $person)
    {
        $this->roleCheck();
        $year = $this->getYear();

        return response()->json(['person' => Intake::retrieveIdsForYear([$person->id], $year, false)[0]]);
    }

    /**
     * Append a note and/or set a ranking for a type & year
     *
     * @param Person $person
     * @return \Illuminate\Http\JsonResponse
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
            $rank = $params['ranking'];
            $rankAttr = $type . "_rank";
            $intake = PersonIntake::findForPersonYearOrNew($personId, $year);
            $intake->{$rankAttr} = $rank;
            if ($intake->isDirty($rankAttr)) {
                $rankUpdated = true;
                $oldRank = $intake->getOriginal($rank);
            } else {
                $rankUpdated = false;
            }
            $intake->save();
            if ($rankUpdated) {
                PersonIntakeNote::record($personId, $year, $type, "rank change [". ($oldRank ?? 'no rank')."] -> [".($rank ?? 'no rank')."]", true);
            }
        }

        return $this->success();
    }

     /**
     * Check for the INTAKE role.
     *
     * @return void
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */

    private function roleCheck() : void {
        if (!$this->userHasRole(Role::INTAKE)) {
            $this->notPermitted('Must have the Intake role');
        }
    }

    /**
     * Log any changes to an intake record
     *
     * @param Person $person person to log
     * @param array $changes the changes to record
     * @param bool $isNew true if the intake record was created
     * @param PersonIntake $intake record itself
     */

    private function logIntakeChanges(Person $person, array $changes, bool $isNew, PersonIntake $intake)
    {
        if (empty($changes)) {
            return;
        }

        if (!$isNew) {
            $changes['id'] = $intake->id;
        }

        $this->log($isNew ? 'person-intake-create' : 'person-intake-update', '', $isNew ? $intake : $changes, $person->id);
    }
}

<?php

namespace App\Lib;

use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\PersonMentor;
use App\Models\PersonMessage;
use App\Models\PersonPosition;
use App\Models\Position;
use App\Models\Role;
use App\Models\SurveyAnswer;
use App\Models\TeamManager;
use App\Models\Timesheet;

class UserInfo
{
    /**
     * Build up a user information record. Use exclusively to generate menus, and build the Me pages.
     *
     * @param Person $person
     * @return array
     */

    public static function build(Person $person): array
    {
        $year = current_year();

        $personId = $person->id;
        $person->retrieveRoles();
        $event = PersonEvent::firstOrNewForPersonYear($personId, $year);
        $timesheet = Timesheet::findPersonOnDuty($personId);

        $arts = [];
        foreach ($person->roles as $role) {
            if (($role & Role::ROLE_BASE_MASK) == Role::ART_TRAINER_BASE) {
                $positionId = $role & Role::POSITION_MASK;
                $arts[] = [
                    'id' => $positionId,
                    'title' => Position::retrieveTitle($positionId)
                ];
            }
        }

        $data = [
            'id' => $personId,
            'callsign' => $person->callsign,
            'callsign_approved' => $person->callsign_approved,
            'status' => $person->status,
            'bpguid' => $person->bpguid,
            'employee_id' => $person->employee_id,
            'roles' => $person->roles,
            'true_roles' => $person->trueRoles,
            'teacher' => [
                'is_trainer' => $person->hasRole([Role::ADMIN, Role::TRAINER]),
                'is_mentor' => $person->hasRole([Role::ADMIN, Role::MENTOR]),
                'have_mentored' => PersonMentor::haveMentees($personId),
                'have_feedback' => SurveyAnswer::haveTrainerFeedback($personId),
                'arts' => $arts,
            ],
            'unread_message_count' => PersonMessage::countUnread($personId),
            'may_request_stickers' => PVR::isEligible($personId, $event, $year),
            'motorpool_policy_enabled' => setting('MotorPoolProtocolEnabled'),
            'onduty_position' => $timesheet?->buildOnDutyInfo(),

            'is_team_manager' => $person->isAdmin() || TeamManager::isManagerOfAny($personId),

            // Years breakdown
            'years_as_contributor' => $person->years_as_contributor,
            'years_as_ranger' => $person->years_as_ranger,
            'years_combined' => $person->years_combined,
            'years_seen' => $person->years_seen,
        ];

        if (in_array($person->status, Person::ACTIVE_STATUSES) || $person->status == Person::NON_RANGER) {
            $data['mvr_eligible'] = MVR::isEligible($personId, $event, $year);
            $data['mvr_potential'] = Position::haveVehiclePotential('mvr', $personId);

            $data['pvr_eligible'] = PVR::isEligible($personId, $event, $year);
            $data['pvr_potential'] = Position::haveVehiclePotential('pvr', $personId);
        }

        return $data;
    }
}
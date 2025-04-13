<?php

namespace App\Http\Controllers;

use App\Exceptions\UnacceptableConditionException;
use App\Lib\BulkLookup;
use App\Lib\Membership;
use App\Lib\Milestones;
use App\Lib\PersonAdvancedSearch;
use App\Lib\PersonSearch;
use App\Lib\Reports\AlphaShirtsReport;
use App\Lib\Reports\PeopleByLocationReport;
use App\Lib\Reports\PeopleByStatusReport;
use App\Lib\Reports\RecommendStatusChangeReport;
use App\Lib\Reports\TimesheetWorkSummaryReport;
use App\Lib\TicketsAndProvisionsProgress;
use App\Lib\UserInfo;
use App\Mail\AccountCreationMail;
use App\Models\Person;
use App\Models\PersonEventInfo;
use App\Models\PersonMentor;
use App\Models\PersonMessage;
use App\Models\PersonPosition;
use App\Models\PersonRole;
use App\Models\PersonStatus;
use App\Models\PositionRole;
use App\Models\Role;
use App\Models\TeamRole;
use App\Models\Timesheet;
use App\Models\Training;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PersonController extends ApiController
{
    /**
     * Show a set of person records based on the given criteria.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $this->authorize('index', Person::class);

        $params = request()->validate([
            'callsign' => 'sometimes|string',
            'statuses' => 'sometimes|string',
            'exclude_statuses' => 'sometimes|string',
            'limit' => 'sometimes|integer',
            'offset' => 'sometimes|integer',
        ]);

        $results = Person::findForQuery($params);

        return $this->toRestFiltered($results['people'], ['limit' => $results['limit'], 'total' => $results['total']], 'person');
    }

    /**
     * Fuzzy search for a person by callsign, fka, email (current or old), and/or real name.
     * Primarily used by the various autocomplete search bars.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function search(): JsonResponse
    {
        $this->authorize('search', Person::class);

        $params = request()->validate([
            'query' => 'required|string',
            'search_fields' => 'required|string',
            'statuses' => 'sometimes|string',
            'exclude_statuses' => 'sometimes|string',
            'limit' => 'sometimes|integer',
            'offset' => 'sometimes|integer',
            'status_groups' => 'sometimes|boolean',
        ]);

        return response()->json(PersonSearch::execute($params, $this->userCanViewEmail()));
    }

    /**
     * Advanced search
     *
     * @return JsonResponse
     * @throws AuthorizationException|UnacceptableConditionException
     */

    public function advancedSearch(): JsonResponse
    {
        $this->authorize('advancedSearch', Person::class);

        $params = request()->validate([
            'statuses' => 'sometimes|string',
            'status_year' => 'sometimes|integer',
            'year_created' => 'sometimes|integer',
            'years' => 'sometimes|integer',
            'years_types' => 'sometimes|string',
            'years_worked' => 'sometimes|integer',
            'years_worked_op' => [
                'sometimes',
                'string',
                Rule::in(['eq', 'lte', 'gte']),
            ],
            'include_years_worked' => 'sometimes|boolean',
            'photo_status' => 'sometimes|string',
            'include_photo_status' => 'sometimes|boolean',

            'online_course_status' => [
                'sometimes',
                'string',
                Rule::in(['missing', 'started', 'completed'])
            ],
            'include_online_course' => 'sometimes|boolean',

            'training_status' => [
                'sometimes',
                'string',
                Rule::in(['missing', 'signed-up', 'passed', 'failed'])
            ],
            'include_training_status' => 'sometimes|boolean',

            'ticketing_status' => [
                'sometimes',
                'string',
                Rule::in(['started', 'not-started', 'finished', 'not-finished', 'not-finished-claimed'])
            ],
            'include_ticketing_info' => 'sometimes|boolean',
        ]);

        return response()->json(PersonAdvancedSearch::execute($params, $this->userCanViewEmail()));
    }

    /**
     * Create a person
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', Person::class);
        $person = new Person;
        $this->fromRest($person);

        if (!$person->save()) {
            return $this->restError($person);
        }

        return $this->success($person);
    }

    /**
     * Show a specific person - include roles, and languages.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(Person $person): JsonResponse
    {
        $this->authorize('view', $person);
        $personId = $person->id;

        return $this->toRestFiltered($person);
    }

    /**
     * Update a person record. Also update the person_language table at the same time.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function update(Person $person): JsonResponse
    {
        $this->authorize('update', $person);

        $this->fromRestFiltered($person);
        $person->retrieveRoles();

        $person->auditReason = 'person update';
        if ($person->has_reviewed_pi) {
            $person->reviewed_pi_at = now();
            $period = setting('DashboardPeriod');
            if ($period != 'after-event' && $period != 'post-event') {
                $person->pi_reviewed_for_dashboard_at = now();
            }
        }

        if (!$person->save()) {
            return $this->restError($person);
        }

        return $this->toRestFiltered($person);
    }

    /**
     * Remove the person from the clubhouse
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(Person $person): JsonResponse
    {
        $this->authorize('delete', $person);
        $person->delete();
        return $this->restDeleteSuccess();
    }

    /**
     * Return the person's training status, provisions, signatures for a given year.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function eventInfo(Person $person): JsonResponse
    {
        $this->authorize('eventInfo', $person);
        return response()->json(['event_info' => PersonEventInfo::findForPersonYear($person, $this->getYear())]);
    }

    /**
     * Change password
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function password(Person $person): JsonResponse
    {
        $this->authorize('password', $person);

        /*
         * Require the old password if person == user
         * and are not an admin.
         * The policy will only allow a change issued by the user or an admin
         */

        $requireOld = $this->isUser($person) && !$this->userHasRole(Role::ADMIN);

        $rules = [
            'password' => 'required|string|confirmed|max:30',
            'password_confirmation' => 'required|string|max:30',
        ];

        $token = request()->input('temp_token');
        if (empty($token) && $requireOld) {
            $rules['password_old'] = 'required|string';
        }

        $passwords = request()->validate($rules);

        if (!empty($token)) {
            if ($person->tpassword != $token || $person->tpassword_expire <= now()->timestamp) {
                return $this->restError('The password reset token is no longer valid.', 422);
            }
        } else if ($requireOld && !$person->isValidPassword($passwords['password_old'])) {
            return $this->restError('The old password does not match.', 422);
        }

        $this->log('person-password', 'Password changed', null, $person->id);
        $person->changePassword($passwords['password']);

        return $this->success();
    }

    /**
     * Retrieve the positions held
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function positions(Person $person): JsonResponse
    {
        $params = request()->validate([
            'include_training' => 'sometimes|boolean',
            'include_mentee' => 'sometimes|boolean',
            'explicit' => 'sometimes|boolean',
            'year' => 'required_if:include_training,true|integer'
        ]);

        $this->authorize('view', $person);

        $includeTraining = $params['include_training'] ?? false;
        $includeMentee = $params['include_mentee'] ?? false;
        $explicit = $params['explicit'] ?? false;

        if ($includeTraining) {
            $positions = Training::findPositionsWithTraining($person, $params['year']);
        } else {
            $positions = PersonPosition::findForPerson($person->id, $includeMentee, $explicit);
        }

        return response()->json(['positions' => $positions]);
    }

    /**
     * Update the positions to be  explicitly held or revoked.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function updatePositions(Person $person): JsonResponse
    {
        $this->authorize('updatePositions', $person);
        $params = request()->validate([
            'position_ids' => 'sometimes|array',
            'position_ids.*' => 'sometimes|integer',
            'grant_ids' => 'sometimes|array',
            'grant_ids.*' => 'sometimes|integer',
            'revoke_ids' => 'sometimes|array',
            'revoke_ids.*' => 'sometimes|integer',
        ]);

        $personId = $person->id;
        Membership::updatePositionsForPerson(
            $this->user->id,
            $person->id,
            $params['position_ids'] ?? null,
            $params['grant_ids'] ?? null,
            $params['revoke_ids'] ?? null,
            'person update',
            $this->userHasRole(Role::ADMIN)
        );
        return response()->json(['positions' => PersonPosition::findForPerson($personId)]);
    }

    /**
     * Retrieve the roles held, and return a list of role ids
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function roles(Person $person): JsonResponse
    {
        $this->authorize('view', $person);

        $results = [
            'roles' => PersonRole::findRolesForPerson($person->id)
        ];

        if (request()->input('include_memberships')) {
            $results['team_roles'] = TeamRole::findRolesForPerson($person->id);
            $results['position_roles'] = PositionRole::findRolesForPerson($person);
        }

        return response()->json($results);
    }

    /**
     * Update the roles held. The Tech Ninja and Admin roles may only be alerted by Tech Ninja role holders.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function updateRoles(Person $person): JsonResponse
    {
        $this->authorize('updateRoles', $person);
        $params = request()->validate(
            [
                'role_ids' => 'present|array',
                'role_ids.*' => 'sometimes|integer'
            ]
        );

        $personId = $person->id;

        $roleIds = $params['role_ids'];
        $existingRoles = PersonRole::findRoleIdsForPerson($personId);
        $newIds = [];
        $deleteIds = [];

        // Only tech ninjas may grant/revoke the tech ninja roles. Ignore attempts to alter the roles by
        // mere mortals.
        $isTechNinja = $this->userHasRole(Role::TECH_NINJA);

        // Find the new ids to be added
        foreach ($roleIds as $id) {
            if (!in_array($id, $existingRoles) && ($id != Role::TECH_NINJA || $isTechNinja)) {
                $newIds[] = $id;
            }
        }

        // Find the ids to be deleted
        foreach ($existingRoles as $id) {
            if (!in_array($id, $roleIds) && ($id != Role::TECH_NINJA || $isTechNinja)) {
                $deleteIds[] = $id;
            }
        }

        // Mass delete the old ids
        if (!empty($deleteIds)) {
            PersonRole::removeIdsFromPerson($personId, $deleteIds, 'person update');
        }

        if (!empty($newIds)) {
            PersonRole::addIdsToPerson($personId, $newIds, 'person update');
        }

        return response()->json(['roles' => PersonRole::findRolesForPerson($personId)]);
    }

    /**
     * Retrieve the team & position membership
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function membership(Person $person): JsonResponse
    {
        $this->authorize('view', $person);
        return response()->json(['membership' => Membership::retrieveForPerson($person->id)]);
    }

    /**
     * Update team memberships
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function updateTeams(Person $person): JsonResponse
    {
        $this->authorize('updateTeams', $person);

        $params = request()->validate([
            'team_ids' => 'sometimes|array',
            'team_ids.*' => 'sometimes|integer',
            'manager_ids' => 'sometimes|array',
            'manager_ids.*' => 'sometimes|integer',
            'grant_ids' => 'sometimes|array',
            'grant_ids.*' => 'sometimes|integer',
            'revoke_ids' => 'sometimes|array',
            'revoke_ids.*' => 'sometimes|integer',
        ]);

        $userId = $this->user->id;
        $isAdmin = $this->user->isAdmin();

        Membership::updateTeamsForPerson($userId,
            $person->id,
            $params['team_ids'] ?? null,
            $params['grant_ids'] ?? null,
            $params['revoke_ids'] ?? null,
            'person update',
            $isAdmin);

        $managerIds = $params['manager_ids'] ?? null;
        if ($managerIds !== null) {
            Membership::updateManagementForPerson($userId, $person->id, $managerIds, 'person update', $isAdmin);
        }

        return $this->success();
    }

    /**
     * /**
     * Return the count of  unread messages
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function unreadMessageCount(Person $person): JsonResponse
    {
        $this->authorize('view', $person);

        return response()->json(['unread_message_count' => PersonMessage::countUnread($person->id)]);
    }

    /**
     * Retrieve user information needed for user login, or to show/edit a person.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function userInfo(Person $person): JsonResponse
    {
        $this->authorize('view', $person);

        return response()->json(['user_info' => UserInfo::build($person)]);
    }

    /**
     * Check see if the person is on duty.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function onDuty(Person $person): JsonResponse
    {
        $this->authorize('view', $person);

        $timesheet = Timesheet::findPersonOnDuty($person->id);
        return response()->json(['onduty' => $timesheet?->buildOnDutyInfo()]);
    }

    /**
     * Calculate how many earned credits for a given year
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function credits(Person $person): JsonResponse
    {
        $params = request()->validate([
            'year' => 'integer|required'
        ]);

        $this->authorize('view', $person);

        return response()->json([
            'credits' => Timesheet::earnedCreditsForYear($person->id, $params['year'])
        ]);
    }

    /**
     * Provide summary for a person
     */

    public function timesheetSummary(Person $person): JsonResponse
    {
        $this->authorize('view', $person);
        $year = $this->getYear();
        return response()->json([
            'summary' => TimesheetWorkSummaryReport::execute($person->id, $year)
        ]);
    }

    /**
     * Find the person's mentees
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function mentees(Person $person): JsonResponse
    {
        $this->authorize('mentees', $person);
        return response()->json(['mentees' => PersonMentor::retrieveAllForPerson($person->id)]);
    }

    /**
     * Retrieve a person's mentors
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function mentors(Person $person): JsonResponse
    {
        $this->authorize('mentors', $person);

        return response()->json(['mentors' => PersonMentor::retrieveMentorHistory($person->id, PersonMentor::retrieveBulkMentorHistory([$person->id]))]);
    }

    /**
     * Register a new account, only supporting auditors right now.
     *
     * Note: method does not require authorization/login. see routes/api.php
     */

    public function register(): JsonResponse
    {
        prevent_if_ghd_server('Account registration');

        if (setting('AuditorRegistrationDisabled')) {
            throw new UnacceptableConditionException("Auditor registration is disabled at this time.");
        }

        $params = request()->validate([
            'intent' => 'required|string',
            'person.email' => 'required|email',
            'person.password' => 'required|string',
            'person.first_name' => 'required|string',
            'person.preferred_name' => 'sometimes|string',
            'person.mi' => 'sometimes|string',
            'person.last_name' => 'required|string',
            'person.street1' => 'required|string',
            'person.street2' => 'sometimes|string',
            'person.apt' => 'sometimes|string',
            'person.city' => 'required|string',
            'person.state' => 'required|string',
            'person.zip' => 'required|string',
            'person.country' => 'required|string',
            'person.status' => 'required|string',
            'person.home_phone' => 'sometimes|string',
            'person.alt_phone' => 'sometimes|string',
        ]);

        $intent = $params['intent'];

        $person = new Person;
        $person->fill($params['person']);

        $person->status = Person::AUDITOR;
        $person->resetCallsign();

        $person->auditReason = 'registration';
        if (!$person->save()) {
            // Ah, crapola. Something nasty happened that shouldn't have.
            $this->log('person-create-fail', 'database creation error', ['person' => $person, 'errors' => $person->getErrors()]);
            return $this->restError($person);
        }

        // Log account creation
        mail_send(new AccountCreationMail('success', 'account created', $person, $intent));

        // Set the password
        $person->changePassword($params['person']['password']);

        // Setup the default roles & positions
        PersonRole::resetRoles($person->id, 'registration', Person::ADD_NEW_USER);
        PersonPosition::resetPositions($person->id, 'registration', Person::ADD_NEW_USER);

        // Record the initial status for tracking through the Unified Flagging View
        PersonStatus::record($person->id, '', Person::AUDITOR, 'registration', $person->id);


        return response()->json(['status' => 'success']);
    }

    /**
     * Prospective / Alpha estimated shirts report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function alphaShirts(): JsonResponse
    {
        $this->authorize('alphaShirts', [Person::class]);

        return response()->json(['alphas' => AlphaShirtsReport::execute()]);
    }

    /**
     * People By Location report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function peopleByLocation(): JsonResponse
    {
        $this->authorize('peopleByLocation', [Person::class]);

        $params = request()->validate([
            'year' => 'sometimes|integer',
        ]);

        $year = $params['year'] ?? current_year();

        return response()->json(['people' => PeopleByLocationReport::execute($year, $this->userCanViewEmail())]);
    }

    /**
     * People By Status report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function peopleByStatus(): JsonResponse
    {
        $this->authorize('peopleByStatus', [Person::class]);

        return response()->json(['statuses' => PeopleByStatusReport::execute()]);
    }

    /**
     * People By Status Change Report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function peopleByStatusChange(): JsonResponse
    {
        $this->authorize('peopleByStatusChange', Person::class);
        return response()->json(RecommendStatusChangeReport::execute($this->getYear()));
    }

    /**
     * Return the milestones a person has hits or needs to hit.
     * (signed up for training, have photo, taken surveys, etc.)
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function milestones(Person $person): JsonResponse
    {
        $this->authorize('view', $person);
        return response()->json(['milestones' => Milestones::buildForPerson($person)]);
    }

    /**
     * Retrieve status history
     * @throws AuthorizationException
     */

    public function statusHistory(Person $person): JsonResponse
    {
        $this->authorize('statusHistory', Person::class);
        return response()->json(['history' => PersonStatus::retrieveAllForId($person->id)]);
    }

    /**
     * Lookup accounts by callsign and/or email
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function bulkLookup(): JsonResponse
    {
        $this->authorize('bulkLookup', Person::class);
        $params = request()->validate([
            'people' => 'required|array',
            'people.*' => 'required|string',
        ]);

        return response()->json([
            'people' => BulkLookup::retrieveByCallsignOrEmail($params['people'])
        ]);
    }

    /**
     * Report on how the person is doing with earning tickets and provisions (meals, showers, clothing.)
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException|UnacceptableConditionException
     */

    public function ticketsProvisionsProgress(Person $person): JsonResponse
    {
        if (!in_array($person->status, Person::LIVE_STATUSES)) {
            throw new UnacceptableConditionException('Person may not earn tickets and provisions.');
        }
        $this->authorize('ticketsProvisionsProgress', $person);
        return response()->json([
            'progress' => TicketsAndProvisionsProgress::compute($person)
        ]);
    }

    /**
     * Reset/release a callsign.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws UnacceptableConditionException
     * @throws ValidationException
     */

    public function releaseCallsign(Person $person): JsonResponse
    {
        $this->authorize('releaseCallsign', $person);

        if ($person->vintage) {
            throw new UnacceptableConditionException("Callsign is vintage and cannot be reset");
        }

        if (!$person->resetCallsign()) {
            throw new UnacceptableConditionException("Unable to reset the callsign");
        }

        $person->callsign_approved = false;
        $person->auditReason = 'callsign released';
        $person->save();


        return response()->json(['callsign' => $person->callsign]);
    }
}


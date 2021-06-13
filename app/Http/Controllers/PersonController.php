<?php

namespace App\Http\Controllers;

use App\Lib\BulkLookup;
use App\Lib\Milestones;
use App\Lib\Reports\TimesheetWorkSummaryReport;
use App\Mail\AccountCreationMail;
use App\Mail\NotifyVCEmailChangeMail;
use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\PersonEventInfo;
use App\Models\PersonLanguage;
use App\Models\PersonMentor;
use App\Models\PersonMessage;
use App\Models\PersonPhoto;
use App\Models\PersonPosition;
use App\Models\PersonRole;
use App\Models\PersonStatus;
use App\Models\Position;
use App\Models\Role;
use App\Models\SurveyAnswer;
use App\Models\Timesheet;
use App\Models\Training;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PersonController extends ApiController
{
    /**
     * Search and display person rows.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function index()
    {
        $this->authorize('index', Person::class);

        $params = request()->validate([
            'query' => 'sometimes|string',
            'search_fields' => 'sometimes|string',
            'statuses' => 'sometimes|string',
            'exclude_statuses' => 'sometimes|string',
            'limit' => 'sometimes|integer',
            'offset' => 'sometimes|integer',
            'basic' => 'sometimes|boolean',
        ]);

        $results = Person::findForQuery($params);
        $people = $results['people'];
        $meta = ['limit' => $results['limit'], 'total' => $results['total']];

        if ($params['basic'] ?? false) {
            if (!$this->userHasRole([Role::ADMIN, Role::MANAGE, Role::VC, Role::MENTOR, Role::TRAINER])) {
                $this->notPermitted("Not authorized for basic search.");
            }

            $rows = [];
            $canViewEmail = $this->userCanViewEmail();
            $searchFields = $params['search_fields'] ?? '';
            $query = trim($params['query'] ?? '');

            if (stripos($searchFields, 'email') !== false) {
                $searchingForEmail = stripos($query, '@') !== false;
            } else {
                $searchingForEmail = false;
            }

            $searchingForFKA = stripos($searchFields, 'formerly_known_as') !== false;
            foreach ($results['people'] as $person) {
                $row = [
                    'id' => $person->id,
                    'callsign' => $person->callsign,
                    'status' => $person->status,
                    'first_name' => $person->first_name,
                    'last_name' => $person->last_name,
                ];

                if ($canViewEmail) {
                    $row['email'] = $person->email;
                }

                if ($searchingForEmail) {
                    if (strcasecmp($person->email, $query) == 0) {
                        $row['email_match'] = 'full';
                    } elseif (stripos($person->email, $query) !== false) {
                        $row['email_match'] = 'partial';
                    }
                }

                if ($searchingForFKA) {
                    foreach ($person->formerlyKnownAsArray(true) as $fka) {
                        if (stripos($fka, $query) !== false) {
                            $row['fka_match'] = $fka;
                            break;
                        }
                    }
                }

                $rows[] = $row;
            }

            return response()->json(['person' => $rows, 'meta' => $meta]);
        } else {
            return $this->toRestFiltered($people, $meta, 'person');
        }
    }

    /*
     * Create a person
     * TODO
     */

    public function store(Request $request)
    {
        $this->authorize('store');
        throw new RuntimeException('unimplemented');
    }

    /*
     * Show a specific person - include roles, and languages.
     */

    public function show(Person $person)
    {
        $this->authorize('view', $person);
        $personId = $person->id;
        $person->languages = PersonLanguage::retrieveForPerson($personId);

        return $this->toRestFiltered($person);
    }

    /*
     * Update a person record. Also update the person_language table at the same time.
     */

    public function update(Request $request, Person $person)
    {
        $this->authorize('update', $person);

        $this->fromRestFiltered($person);
        $person->retrieveRoles();

        $statusChanged = false;
        if ($person->isDirty('status')) {
            $statusChanged = true;
            $newStatus = $person->status;
            $oldStatus = $person->getOriginal('status');
        }

        $emailChanged = false;
        if ($person->isDirty('email')) {
            $emailChanged = true;
            $oldEmail = $person->getOriginal('email');
        }

        $person->auditReason = 'person update';

        if ($person->has_reviewed_pi) {
            $person->reviewed_pi_at = now();
        }

        if (!$person->save()) {
            return $this->restError($person);
        }

        if ($person->languages !== null) {
            PersonLanguage::updateForPerson($person->id, $person->languages);
        }

        // Alert VCs when the email address changes for a prospective.
        if ($emailChanged
            && $person->status == Person::PROSPECTIVE
            && $person->id == $this->user->id) {
            mail_to(setting('VCEmail'), new NotifyVCEmailChangeMail($person, $oldEmail), true);
        }

        if ($statusChanged) {
            $person->changeStatus($newStatus, $oldStatus, 'person update');
            $person->saveWithoutValidation();
        }

        $person->languages = PersonLanguage::retrieveForPerson($person->id);

        return $this->toRestFiltered($person);
    }

    /*
     * Remove the person from the clubhouse
     */
    public function destroy(Person $person)
    {
        $this->authorize('delete', $person);

        DB::transaction(
            function () use ($person) {
                $personId = $person->id;

                DB::update('UPDATE slot SET signed_up = signed_up - 1 WHERE id IN (SELECT slot_id FROM person_slot WHERE person_id=?)', [$personId]);

                foreach (Person::ASSOC_TABLES as $table) {
                    DB::table($table)->where('person_id', $personId)->delete();
                }

                // Photos require a bit of extra work.
                PersonPhoto::deleteAllForPerson($personId);

                // Farewell, parting is such sweet sorrow . . .
                $person->delete();
            }
        );

        return $this->restDeleteSuccess();
    }

    /*
     * Return the person's training status, BMID & radio privs
     * for a given year.
     */

    public function eventInfo(Person $person)
    {
        $year = $this->getYear();
        $eventInfo = PersonEventInfo::findForPersonYear($person, $year);
        if ($eventInfo) {
            return response()->json(['event_info' => $eventInfo]);
        }

        return $this->restError('The year could not be found.', 404);
    }

    /*
     * Change password
     */

    public function password(Request $request, Person $person)
    {
        $this->authorize('password', $person);

        /*
         * Require the old password if person == user
         * and are not an admin.
         * The policy will only allow a change issued by the user or an admin
         */

        $requireOld = $this->isUser($person) && !$this->userHasRole(Role::ADMIN);

        $rules = [
            'password' => 'required|string|confirmed',
            'password_confirmation' => 'required|string',
        ];

        $token = request()->input('temp_token');
        if (empty($token) && $requireOld) {
            $rules['password_old'] = 'required|string';
        }

        $passwords = $request->validate($rules);

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

    /*
     * Retrieve the positions held
     */

    public function positions(Person $person)
    {
        $params = request()->validate([
            'include_training' => 'sometimes|boolean',
            'include_mentee' => 'sometimes|boolean',
            'year' => 'required_if:include_training,true|integer'
        ]);

        $this->authorize('view', $person);

        $includeTraining = $params['include_training'] ?? false;
        $includeMentee = $params['include_mentee'] ?? false;

        if ($includeTraining) {
            $positions = Training::findPositionsWithTraining($person, $params['year']);
        } else {
            $positions = PersonPosition::findForPerson($person->id, $includeMentee);
        }

        return response()->json(['positions' => $positions]);
    }

    /*
     * Update the positions held
     */

    public function updatePositions(Person $person)
    {
        $this->authorize('updatePositions', $person);
        $params = request()->validate(
            [
                'position_ids' => 'present|array',
                'position_ids.*' => 'sometimes|integer'
            ]
        );

        $personId = $person->id;

        $positionIds = $params['position_ids'];
        $positions = PersonPosition::findForPerson($personId);
        $newIds = [];
        $deleteIds = [];

        // Find the new ids
        foreach ($positionIds as $id) {
            $found = false;
            foreach ($positions as $position) {
                if ($position->id == $id) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $newIds[] = $id;
            }
        }

        // Find the ids to be deleted
        foreach ($positions as $position) {
            if (!in_array($position->id, $positionIds)) {
                $deleteIds[] = $position->id;
            }
        }

        // Mass delete the old ids
        if (!empty($deleteIds)) {
            PersonPosition::removeIdsFromPerson($personId, $deleteIds, 'person update');
        }

        if (!empty($newIds)) {
            PersonPosition::addIdsToPerson($personId, $newIds, 'person update');
        }

        return response()->json(['positions' => PersonPosition::findForPerson($personId)]);
    }

    /*
    * Retrieve the roles held, and return a list of role ids
    */

    public function roles(Person $person)
    {
        $this->authorize('view', $person);

        return response()->json(['roles' => PersonRole::findRolesForPerson($person->id)]);
    }

    /*
     * Update the roles held
     */

    public function updateRoles(Person $person)
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

        // Only tech ninjas may grant/revoke the tech ninja role. Ignore attempts to alter the role by
        // mere mortals.
        $isTechNinja = $this->userHasRole(Role::TECH_NINJA);

        // Find the new ids
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

    /*
     * Return the count of  unread messages
     */

    public function unreadMessageCount(Person $person)
    {
        $this->authorize('view', $person);

        return response()->json(['unread_message_count' => PersonMessage::countUnread($person->id)]);
    }

    /*
     * Retrieve user information needed for user login, or to show/edit a person.
     */

    public function userInfo(Person $person)
    {
        $this->authorize('view', $person);

        $personId = $person->id;
        $person->retrieveRoles();
        $isArtTrainer = $person->hasRole(Role::ART_TRAINER);
        $event = PersonEvent::firstOrNewForPersonYear($personId, current_year());

        $timesheet = Timesheet::findPersonOnDuty($personId);
        if ($timesheet) {
            $onduty = [
                'id' => $timesheet->position_id,
                'title' => $timesheet->position->title,
                'type' => $timesheet->position->type,
                'subtype' => $timesheet->position->subtype,
            ];
        } else {
            $onduty = null;
        }

        $data = [
            'id' => $personId,
            'callsign' => $person->callsign,
            'callsign_approved' => $person->callsign_approved,
            'status' => $person->status,
            'bpguid' => $person->bpguid,
            'roles' => $person->roles,
            'teacher' => [
                'is_trainer' => $person->hasRole([Role::ADMIN, Role::TRAINER]),
                'is_art_trainer' => $isArtTrainer,
                'is_mentor' => $person->hasRole([Role::ADMIN, Role::MENTOR]),
                'have_mentored' => PersonMentor::haveMentees($personId),
                'have_feedback' => SurveyAnswer::haveTrainerFeedback($personId),
            ],
            'unread_message_count' => PersonMessage::countUnread($personId),
            'has_hq_window' => PersonPosition::havePosition($personId, Position::HQ_WORKERS),
            'may_request_stickers' => $event->may_request_stickers,
            'onduty_position' => $onduty,

            // Years breakdown
            'years' => Timesheet::findYears($personId, Timesheet::YEARS_WORKED),
            'all_years' => Timesheet::findYears($personId, Timesheet::YEARS_ALL),
            'rangered_years' => Timesheet::findYears($personId, Timesheet::YEARS_RANGERED),
            'non_ranger_years' => Timesheet::findYears($personId, Timesheet::YEARS_NON_RANGERED),
        ];

        /*
         * In the future the ART training positions might be limited to
         * a specific set instead of everything for all ART_TRAINERs.
         */

        if ($isArtTrainer) {
            $data['teacher']['arts'] = Position::findAllTrainings(true);
        }

        return response()->json(['user_info' => $data]);
    }

    /**
     * Return the years person has worked and has sign ups
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function years(Person $person)
    {
        $this->authorize('view', $person);
        $personId = $person->id;
        return response()->json([
            'years' => Timesheet::findYears($personId, Timesheet::YEARS_WORKED),
            'all_years' => Timesheet::findYears($personId, Timesheet::YEARS_ALL),
            'rangered_years' => Timesheet::findYears($personId, Timesheet::YEARS_RANGERED),
            'non_ranger_years' => Timesheet::findYears($personId, Timesheet::YEARS_NON_RANGERED),
        ]);
    }

    /*
     * Calculate how many earned credits for a given year
     */

    public function credits(Person $person)
    {
        $params = request()->validate([
            'year' => 'integer|required'
        ]);

        $this->authorize('view', $person);

        return response()->json([
            'credits' => Timesheet::earnedCreditsForYear($person->id, $params['year'])
        ]);
    }

    /*
     * Provide summary for a person
     */

    public function timesheetSummary(Person $person)
    {
        $this->authorize('view', $person);
        $year = $this->getYear();
        return response()->json([
            'summary' => TimesheetWorkSummaryReport::execute($person->id, $year)
        ]);
    }

    /*
     * Find the person's mentees
     */

    public function mentees(Person $person)
    {
        $this->authorize('mentees', $person);
        return response()->json(['mentees' => PersonMentor::retrieveAllForPerson($person->id)]);
    }

    /*
     * Retrieve a person's mentors
     */

    public function mentors(Person $person)
    {
        $this->authorize('mentors', $person);

        return response()->json(['mentors' => PersonMentor::retrieveMentorHistory($person->id)]);
    }

    /**
     * Register a new account, only supporting auditors right now.
     *
     * Note: method does not require authorization/login. see routes/api.php
     */

    public function register()
    {
        if (setting('AuditorRegistrationDisabled')) {
            throw new InvalidArgumentException("Auditor registration is disabled at this time.");
        }

        $params = request()->validate([
            'intent' => 'required|string',
            'person.email' => 'required|email',
            'person.password' => 'required|string',
            'person.first_name' => 'required|string',
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

        $accountCreateEmail = setting('AccountCreationEmail');

        $intent = $params['intent'];

        $person = new Person;
        $person->fill($params['person']);

        if ($person->status != Person::AUDITOR) {
            throw new InvalidArgumentException('Only the auditor status is allowed currently for registration.');
        }

        // make the callsign for an auditor.
        if ($person->status == Person::AUDITOR) {
            $person->resetCallsign();
        }

        $person->auditReason = 'registration';
        if (!$person->save()) {
            // Ah, crapola. Something nasty happened that shouldn't have.
            mail_to($accountCreateEmail, new AccountCreationMail('failed', 'database creation error', $person, $intent), true);
            $this->log('person-create-fail', 'database creation error', ['person' => $person, 'errors' => $person->getErrors()]);
            return $this->restError($person);
        }

        // Log account creation
        mail_to($accountCreateEmail, new AccountCreationMail('success', 'account created', $person, $intent), true);

        // Set the password
        $person->changePassword($params['person']['password']);

        // Setup the default roles & positions
        PersonRole::resetRoles($person->id, 'registration', Person::ADD_NEW_USER);
        PersonPosition::resetPositions($person->id, 'registration', Person::ADD_NEW_USER);

        // Record the initial status for tracking through the Unified Flagging View
        PersonStatus::record($person->id, '', Person::AUDITOR, 'registration', $person->id);


        return response()->json(['status' => 'success']);
    }

    /*
     * Prospective / Alpha estimated shirts report
     */

    public function alphaShirts()
    {
        $this->authorize('alphaShirts', [Person::class]);

        $rows = Person::select(
            'id',
            'callsign',
            'status',
            'first_name',
            'last_name',
            'email',
            'longsleeveshirt_size_style',
            'teeshirt_size_style'
        )
            ->whereIn('status', [Person::ALPHA, Person::PROSPECTIVE])
            ->orderBy('callsign')
            ->get();

        return response()->json(['alphas' => $rows]);
    }

    /*
    * People By Location report
    */

    public function peopleByLocation()
    {
        $this->authorize('peopleByLocation', [Person::class]);

        $params = request()->validate([
            'year' => 'sometimes|integer',
        ]);

        $year = $params['year'] ?? current_year();

        return response()->json(['people' => Person::retrievePeopleByLocation($year)]);
    }

    /*
     * People By Role report
     */

    public function peopleByRole()
    {
        $this->authorize('peopleByRole', [Person::class]);

        return response()->json(['roles' => Person::retrievePeopleByRole()]);
    }

    /*
     * People By Status report
     */

    public function peopleByStatus()
    {
        $this->authorize('peopleByStatus', [Person::class]);

        return response()->json(['statuses' => Person::retrievePeopleByStatus()]);
    }

    /*
     * Languages Report
     */

    public function languagesReport()
    {
        $this->authorize('peopleByStatus', [Person::class]);

        return response()->json(['languages' => PersonLanguage::retrieveAllOnSiteSpeakers()]);
    }

    /*
     * People By Status Change Report
     */

    public function peopleByStatusChange()
    {
        $this->authorize('peopleByStatusChange', [Person::class]);
        $year = $this->getYear();
        return response()->json(Person::retrieveRecommendedStatusChanges($year));
    }

    /**
     * Return the milestones a person has hits or needs to hit.
     * (signed up for training, have photo, taken surveys, etc.)
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function milestones(Person $person)
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
     *
     */

    public function bulkLookup()
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
}


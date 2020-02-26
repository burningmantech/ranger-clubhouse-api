<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

use App\Http\Controllers\ApiController;

use App\Helpers\SqlHelper;

use App\Models\ManualReview;
use App\Models\Person;
use App\Models\PersonEventInfo;
use App\Models\PersonLanguage;
use App\Models\PersonMentor;
use App\Models\PersonMessage;
use App\Models\PersonPhoto;
use App\Models\PersonPosition;
use App\Models\PersonRole;
use App\Models\PersonSlot;
use App\Models\PersonStatus;
use App\Models\Position;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Slot;
use App\Models\Timesheet;
use App\Models\TraineeStatus;
use App\Models\Training;

use App\Mail\AccountCreationMail;
use App\Mail\NotifyVCEmailChangeMail;
use App\Mail\WelcomeMail;

use Carbon\Carbon;

class PersonController extends ApiController
{
    /*
     * Search and display person rows.
     */

    public function index()
    {
        $this->authorize('index', Person::class);

        $params = request()->validate([
            'query'            => 'sometimes|string',
            'search_fields'    => 'sometimes|string',
            'statuses'         => 'sometimes|string',
            'exclude_statuses' => 'sometimes|string',
            'limit'            => 'sometimes|integer',
            'offset'           => 'sometimes|integer',
            'basic'            => 'sometimes|boolean',
        ]);

        $results = Person::findForQuery($params);
        $people = $results['people'];
        $meta = [ 'limit' => $results['limit'], 'total' => $results['total'] ];

        if ($params['basic'] ?? false) {
            if (!$this->userHasRole([ Role::ADMIN, Role::MANAGE, Role::VC, Role::MENTOR, Role::TRAINER ])) {
                throw new \InvalidArgumentException("Not authorized for basic search.");
            }

            $rows = [];
            foreach ($results['people'] as $person) {
                $row = [
                    'id'              => $person->id,
                    'callsign'        => $person->callsign,
                    'status'          => $person->status,
                    'first_name'      => $person->first_name,
                    'last_name'       => $person->last_name,
                    'user_authorized' => $person->user_authorized,
                ];

                if ($this->userHasRole([ Role::ADMIN, Role::VIEW_PII, Role::VIEW_EMAIL, Role::VC ])) {
                    $row['email'] = $person->email;
                }

                if (stripos($params['search_fields'] ?? '', 'email') !== false) {
                    $query = $params['query'] ?? '';
                    if (stripos($query, '@') !== false) {
                        if ($person->email == $query) {
                            $row['email_match'] = 'full';
                        } elseif (stripos($person->email, $query) !== false) {
                            $row['email_match'] = 'partial';
                        }
                    }
                }
                $rows[] = $row;
            }

            return response()->json([ 'person' => $rows, 'meta' => $meta ]);
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
    }

    /*
     * Show a specific person - include roles, and languages.
     */

    public function show(Person $person)
    {
        $this->authorize('view', $person);
        $personId = $person->id;
        $person->retrieveRoles();

        $person->languages = PersonLanguage::retrieveForPerson($personId);

        $personId = $this->user->id;

        return $this->toRestFiltered($person);
    }

    /*
     * Update a person record. Also update the person_language table at the same time.
     */

    public function update(Request $request, Person $person)
    {
        $this->authorize('update', $person);

        $params = request()->validate(
            [
            'person.email'  => 'sometimes|email|unique:person,email,'.$person->id.',id'
            ],
            [
            'person.email.unique'   => 'The email address is already used by another account'
            ]
        );

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

        $changes = $person->getChangedValues();

        if (!$person->save()) {
            return $this->restError($person);
        }

        if ($person->languages !== null) {
            PersonLanguage::updateForPerson($person->id, $person->languages);
        }

        // Track changes
        if (!empty($changes)) {
            $this->log('person-update', 'person update', $changes, $person->id);

            // Alert VCs when the email address changes for a prospective.
            if ($emailChanged
            && $person->status == Person::PROSPECTIVE
            && $person->id == $this->user->id) {
                mail_to(setting('VCEmail'), new NotifyVCEmailChangeMail($person, $oldEmail));
            }

            if ($statusChanged) {
                $person->changeStatus($newStatus, $oldStatus, 'person update');
                $person->save();
            }
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

                DB::update('UPDATE slot SET signed_up = signed_up - 1 WHERE id IN (SELECT slot_id FROM person_slot WHERE person_id=?)', [ $personId ]);

                $tables = [
                    //'access_document_changes',
                    'access_document_delivery',
                    'access_document',
                    'action_logs',
                    'alert_person',
                    'asset_person',
                    'bmid',
                    'broadcast_message',
                    //'contact_log',
                    'manual_review',
                    'mentee_status',
                    'person_intake',
                    'person_intake_note',
                    'person_language',
                    'person_mentor',
                    'person_message',
                    'person_position',
                    'person_role',
                    'person_slot',
                    'radio_eligible',
                    'timesheet',
                    'timesheet_log',
                    'timesheet_missing',
                    'trainee_note',
                    'trainee_status',
                    'trainer_status'
                ];

                foreach ($tables as $table) {
                    DB::table($table)->where('person_id', $personId)->delete();
                }

                // Photos require a bit of extra work.
                PersonPhoto::deleteAllForPerson($personId);

                // Farewell, parting is such sweet sorrow . . .
                $person->delete();
            }
        );

        $this->log('person-delete', 'Person delete', [ 'person' => $person ], $person->id);

        return $this->restDeleteSuccess();
    }

    /*
     * Return the person's training status, BMID & radio privs
     * for a given year.
     */

    public function eventInfo(Person $person)
    {
        $year = $this->getYear();
        $eventInfo = PersonEventInfo::findForPersonYear($person->id, $year);
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
              'password' => 'required|confirmed',
              'password_confirmation' => 'required'
          ];

        if ($requireOld) {
            $rules['password_old'] = 'required';
        }

        $passwords = $request->validate($rules);

        if ($requireOld && !$person->isValidPassword($passwords['password_old'])) {
            return $this->restError('The old password does not match.', 422);
        }

        $this->log('person-password', 'Password changed', null, $person->id);
        $person->changePassword($passwords['password']);

        return $this->success();
    }

    /*
     * Retrieve the positions held
     */

    public function positions(Request $request, Person $person)
    {
        $params = request()->validate([
            'include_training'   => 'sometimes|boolean',
            'include_mentee' => 'sometimes|boolean',
            'year'               => 'required_if:include_training,true|integer'
        ]);

        $this->authorize('view', $person);

        $includeTraining = $params['include_training'] ?? false;
        $includeMentee = $params['include_mentee'] ?? false;

        if ($includeTraining) {
            $positions = Training::findPositionsWithTraining($person, $params['year']);
        } else {
            $positions = PersonPosition::findForPerson($person->id, $includeMentee);
        }

        return response()->json([ 'positions' =>  $positions]);
    }
    /*
     * Update the positions held
     */

    public function updatePositions(Request $request, Person $person)
    {
        $this->authorize('updatePositions', $person);
        $params = request()->validate(
            [
            'position_ids'  => 'present|array',
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

        return response()->json([ 'positions' => PersonPosition::findForPerson($personId) ]);
    }

    /*
    * Retrieve the roles held, and return a list of role ids
    */

    public function roles(Request $request, Person $person)
    {
        $this->authorize('view', $person);

        return response()->json([ 'roles' => PersonRole::findRolesForPerson($person->id) ]);
    }

    /*
     * Update the roles held
     */

    public function updateRoles(Request $request, Person $person)
    {
        $this->authorize('updateRoles', $person);
        $params = request()->validate(
            [
            'role_ids'  => 'present|array',
            'role_ids.*' => 'sometimes|integer'
            ]
        );

        $personId = $person->id;

        $roleIds = $params['role_ids'];
        $existingRoles = PersonRole::findRoleIdsForPerson($personId);
        $newIds = [];
        $deleteIds = [];

        // Find the new ids
        foreach ($roleIds as $id) {
            if (!in_array($id, $existingRoles)) {
                $newIds[] = $id;
            }
        }

        // Find the ids to be deleted
        foreach ($existingRoles as $id) {
            if (!in_array($id, $roleIds)) {
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

        return response()->json([ 'roles' => PersonRole::findRolesForPerson($personId) ]);
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

        $isArtTrainer = $person->hasRole([Role::ART_TRAINER, Role::ADMIN]);

        $data = [
            'teacher' => [
                'is_trainer'     => $person->hasRole([Role::ADMIN, Role::TRAINER]),
                'is_art_trainer' => $isArtTrainer,
                'is_mentor'      => $person->hasRole([Role::MENTOR, Role::ADMIN]),
                'have_mentored'  => PersonMentor::haveMentees($person->id)
            ],
            'unread_message_count' => PersonMessage::countUnread($person->id),
            'years' => Timesheet::years($person->id),
            'all_years' => Timesheet::years($person->id, true),
            'has_hq_window' => PersonPosition::havePosition($person->id, Position::HQ_WINDOW),
            'is_on_duty_at_hq' => Timesheet::isPersonSignIn($person->id, [ Position::HQ_WINDOW, Position::HQ_SHORT, Position::HQ_LEAD ])
        ];

        /*
         * In the future the ART training positions might be limited to
         * a specific set intead of everything for all ART_TRAINERs.
         */

        if ($isArtTrainer) {
            $data['teacher']['arts'] = Position::findAllTrainings(true);
        }

        return response()->json([ 'user_info' => $data ]);
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
        $params = request()->validate([
            'year' => 'integer|required'
        ]);

        $this->authorize('view', $person);

        return response()->json([
            'summary' => Timesheet::workSummaryForPersonYear($person->id, $params['year'])
        ]);
    }

    /*
     * Find the person's mentees
     */

    public function mentees(Person $person)
    {
        $this->authorize('mentees', $person);
        return response()->json(['mentees' => PersonMentor::retrieveAllForPerson($person->id) ]);
    }

    /*
     * Retrieve a person's mentors
     */

    public function mentors(Person $person)
    {
        $this->authorize('mentors', $person);

        return response()->json([ 'mentors' => PersonMentor::retrieveMentorHistory($person->id) ]);
    }

    /**
     * Register a new account, only supporting auditors right now.
     *
     * Note: method does not require authorization/login. see routes/api.php
     */

    public function register()
    {
        $params = request()->validate([
            'intent'            => 'required|string',
            'person.email'      => 'required|email',
            'person.password'   => 'required|string',
            'person.first_name' => 'required|string',
            'person.mi'         => 'sometimes|string',
            'person.last_name'  => 'required|string',
            'person.street1'    => 'required|string',
            'person.street2'    => 'sometimes|string',
            'person.apt'        => 'sometimes|string',
            'person.city'       => 'required|string',
            'person.state'      => 'required|string',
            'person.zip'        => 'required|string',
            'person.country'    => 'required|string',
            'person.status'     => 'required|string',
            'person.home_phone' => 'sometimes|string',
            'person.alt_phone'  => 'sometimes|string',
        ]);

        $accountCreateEmail = setting('AccountCreationEmail');

        $intent = $params['intent'];

        $person = new Person;
        $person->fill($params['person']);

        if ($person->status != 'auditor') {
            throw new \InvalidArgumentException('Only the auditor status is allowed currently for registration.');
        }

        if (Person::emailExists($person->email)) {
            // An account already exists with the same email..
            mail_to($accountCreateEmail, new AccountCreationMail('failed', 'duplicate email', $person, $intent));
            $this->log('person-create-fail', 'duplicate email', [ 'person' => $params['person'] ]);
            return response()->json([ 'status' => 'email-exists' ]);
        }

        // make the callsign for an auditor.
        if ($person->status == 'auditor') {
            $person->resetCallsign();
        }

        $person->create_date = SqlHelper::now();

        if (!$person->save()) {
            // Ah, crapola. Something nasty happened that shouldn't have.
            mail_to($accountCreateEmail, new AccountCreationMail('failed', 'database creation error', $person, $intent));
            $this->log('person-create-fail', 'database creation error', [ 'person' => $person, 'errors' => $person->getErrors() ]);
            return $this->restError($person);
        }

        // Log account creation
        mail_to($accountCreateEmail, new AccountCreationMail('success', 'account created', $person, $intent));
        $this->log('person-create', 'registration', null, $person->id);

        // Set the password
        $person->changePassword($params['person']['password']);

        // Setup the default roles & positions
        PersonRole::resetRoles($person->id, 'registration', Person::ADD_NEW_USER);
        PersonPosition::resetPositions($person->id, 'registration', Person::ADD_NEW_USER);

        // Record the initial status for tracking through the Unified Flagging View
        PersonStatus::record($person->id, '', Person::AUDITOR, 'registration', $person->id);

        // Send a welcome email to the person if not an auditor
        if ($person->status != 'auditor' && setting('SendWelcomeEmail')) {
            mail_to($person->email, new WelcomeMail($person));
        }

        return response()->json([ 'status' => 'success' ]);
    }

    /*
     * Prospecitve / Alpha estimated shirts report
     */

    public function alphaShirts()
    {
        $this->authorize('alphaShirts', [ Person::class ]);

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
            ->whereIn('status', [ 'alpha', 'prospective' ])
            ->where('user_authorized', true)
            ->orderBy('callsign')
            ->get();

        return response()->json([ 'alphas' => $rows ]);
    }

    /*
     * Vehicle Paperwork Report
     */

    public function vehiclePaperwork()
    {
        $this->authorize('vehiclePaperwork', [ Person::class ]);

        $rows = DB::table('person')
            ->select('id', 'callsign', 'status', 'vehicle_paperwork', 'vehicle_insurance_paperwork')
            ->where('user_authorized', true)
            ->where(function ($q) {
                $q->where('vehicle_paperwork', true);
                $q->orWhere('vehicle_insurance_paperwork', true);
            })
            ->orderBy('callsign')
            ->get();

        return response()->json([ 'people' => $rows ]);
    }

    /*
     * People By Location report
     */

    public function peopleByLocation()
    {
        $this->authorize('peopleByLocation', [ Person::class ]);

        $params = request()->validate([
            'year'  => 'sometimes|integer',
        ]);

        $year = $params['year'] ?? current_year();

        return response()->json([ 'people' => Person::retrievePeopleByLocation($year) ]);
    }

    /*
     * People By Role report
     */

    public function peopleByRole()
    {
        $this->authorize('peopleByRole', [ Person::class ]);

        return response()->json([ 'roles' => Person::retrievePeopleByRole() ]);
    }

    /*
     * People By Status report
     */

    public function peopleByStatus()
    {
        $this->authorize('peopleByStatus', [ Person::class ]);

        return response()->json([ 'statuses' => Person::retrievePeopleByStatus() ]);
    }

    /*
     * Languages Report
     */

    public function languagesReport()
    {
        $this->authorize('peopleByStatus', [ Person::class ]);

        return response()->json([ 'languages' => PersonLanguage::retrieveAllOnSiteSpeakers() ]);
    }

    /*
     * People By Status Change Report
     */

    public function peopleByStatusChange()
    {
        $this->authorize('peopleByStatusChange', [ Person::class ]);
        $year = $this->getYear();

        return response()->json(Person::retrieveRecommendedStatusChanges($year));
    }

    /*
     * milestone a PNV / auditor has meet various milestones
     */

    public function milestones(Person $person)
    {
        $this->authorize('view', $person);

        $status = $person->status;

/*
        if (!in_array($status, [ Person::PROSPECTIVE, Person::PROSPECTIVE_WAITLIST, Person::ALPHA, Person::AUDITOR ])) {
            throw new \InvalidArgumentException('Person status does not have milestone');
        }
*/

        $year = current_year();
        $now = SqlHelper::now();

        $milestones = [
            'manual_review_passed' => ManualReview::existsPersonForYear($person->id, $year),
            'manual_review_enabled' => setting('ManualReviewLinkEnable'),
            'manual_review_link' => setting('ManualReviewGoogleFormBaseUrl').urlencode($person->callsign),
            'behavioral_agreement' => $person->behavioral_agreement,
            'has_reviewed_pi'  => $person->has_reviewed_pi,
            'photo_upload_enabled'   => setting('PhotoUploadEnable'),
            'trainings_available' => Slot::haveActiveForPosition(Position::TRAINING),
            'alpha_shift_prep_link' => setting('OnboardAlphaShiftPrepLink')
        ];


        $trainings = TraineeStatus::findForPersonYear($person->id, $year, Position::TRAINING);
        $trainingSignups = Schedule::findEnrolledSlots($person->id, $year, Position::TRAINING);

        /*
         * Find a training sign up OR attendance record.
         *
         * A 12 hour window is allowed from the end of the session to be considered
         * pending if no attendance was found, otherwise fail it.
         */

        if (!$trainings->isEmpty()) {
            // Attendance records found
            $passed = $trainings->firstWhere('passed', true);
            if ($passed) {
                // Yay, they passed!
                $milestoneTraining = [
                    'status'     => 'passed',
                    'slot_id' => $passed->id,
                    'begins' => $passed->begins,
                    'description' => $passed->description
                ];
            } else {
                // Not passed.. but has the person signed up for a later training?
                $lastTraining = $trainings->last();
                $lastSignup  = $trainingSignups->last();

                if ($lastSignup
                && (!$lastTraining || ($lastSignup->slot_id != $lastTraining->slot_id || Carbon::parse($lastSignup->begins)->gt($lastTraining->begins)))
                && self::isTimeWithinGracePeriod($lastSignup->ends, $now)) {
                    $milestoneTraining = [
                        'status'      => 'pending',
                        'slot_id'     => $lastSignup->id,
                        'begins'      => $lastSignup->begins,
                        'description' => $lastSignup->description
                    ];
                } else {
                    $slot = $lastSignup ?? $lastTraining;
                    $milestoneTraining = [
                        'status'    => 'failed',
                        'slot_id' => $slot->id,
                        'begins' =>(string) $slot->begins,
                        'description' => $slot->description
                    ];
                }
            }
        } elseif (!$trainingSignups->isEmpty()) {
            // No attendance found yet they are signed up
            $lastSignup = $trainingSignups->last();

            if (self::isTimeWithinGracePeriod($lastSignup->ends, $now)) {
                $milestoneTraining = [
                    'status'     => 'pending',
                    'slot_id' => $lastSignup->id,
                    'begins' => (string) $lastSignup->begins,
                    'description' => $lastSignup->description
                ];
            } else {
                $milestoneTraining = [
                    'status'     => 'failed',
                    'slot_id' => $lastSignup->id,
                    'begins' => (string)$lastSignup->begins,
                    'description' => $lastSignup->description
                ];
            }
        } else {
            // Person has done nada.. y u no sign up?
            $milestoneTraining = [ 'status' => 'missing' ];
        }

        $milestones['training'] = $milestoneTraining;

        if ($status == Person::BONKED) {
            $milestones['bonked'] = true;
        } elseif ($status == Person::ALPHA || $status == Person::PROSPECTIVE) {
            $milestones['alpha_shifts_available'] = Slot::haveActiveForPosition(Position::ALPHA);
            if ($milestones['alpha_shifts_available']) {
                $alphaShift = Schedule::findEnrolledSlots($person->id, $year, Position::ALPHA)->last();
                if ($alphaShift) {
                    if (Carbon::parse($alphaShift->begins)->addHours(24)->lte($now)) {
                        $alphaStatus = 'no-show';
                    } else {
                        $alphaStatus = 'pending';
                    }
                    $milestones['alpha_shift'] = [
                        'slot_id'  => $alphaShift->id,
                        'begins'   => (string) $alphaShift->begins,
                        'status'   => $alphaStatus,
                    ];
                }
            }
        }

        if ($status != Person::AUDITOR) {
            if (empty($person->bpguid)) {
                $milestones['missing_bpguid'] = true;
            }

            $milestones['photo_status'] = PersonPhoto::retrieveStatus($person);
        }

        return response()->json([ 'milestones' => $milestones ]);
    }

    /*
     *  Is the given time within a grace period?
     *  Is the given time within a grace period?
     */

    private function isTimeWithinGracePeriod($time, $now)
    {
        $time = is_string($time) ? Carbon::parse($time) : $time->clone();

        return $time->addHours(12)->gt($now);
    }

    /*
     *  Onboard debugging endpoint. Only available on a staging or local development
     * server.
     */

    public function onboardDebug(Person $person)
    {
        $year = current_year();

        if (config('clubhouse.DeploymentEnvironment') != 'Staging' && !app()->isLocal()) {
            throw new \InvalidArgumentException('Onboard debugging is only available in the staging server');
        }

        $photoStatus = request()->input('photo_status');

        if (!empty($photoStatus)) {
            $photo = PersonPhoto::where('person_id', $person->id)->first();
            if (!$photo) {
                $photo = new PersonPhoto;
                $photo->person_id = $person->id;
                $photo->image_filename = 'test.jpg';
                $photo->orig_filename = 'test-orig.jpg';
            }

            $photo->status = $photoStatus;
            $photo->save();

            return $this->success();
        }

        $status = request()->input('manual_review');
        if (!empty($status)) {
            $mr = ManualReview::findForPersonYear($person->id, $year);
            if (!$mr) {
                if ($status == 'missing') {
                    return $this->success();
                }
                ManualReview::create([ 'person_id' => $person->id, 'passdate' => "$year-01-01 10:00:00"]);
            } elseif ($status == 'missing') {
                $mr->delete();
            }

            return $this->success();
        }

        $status = request()->input('training');

        if (!empty($status)) {
            switch ($status) {
                case 'signup':
                    $slot = Slot::create([
                        'position_id'   => Position::TRAINING,
                        'begins'    => now()->addHours(24),
                        'ends'      => now()->addHours(25),
                        'description' => 'Testing Slot',
                        'active' => true,
                        'max'   => 10
                    ]);
                    PersonSlot::create([ 'person_id' => $person->id, 'slot_id' => $slot->id ]);
                    return $this->success();
                case 'signup-past':
                    $slot = Slot::create([
                        'position_id'   => Position::TRAINING,
                        'begins'    => now()->subHours(24),
                        'ends'      => now()->subHours(23),
                        'description' => 'Past Testing Slot',
                        'active' => true,
                        'max'   => 10
                    ]);
                    PersonSlot::create([ 'person_id' => $person->id, 'slot_id' => $slot->id ]);
                    return $this->success();
                case 'signup-now':
                    $slot = Slot::create([
                        'position_id'   => Position::TRAINING,
                        'begins'    => now(),
                        'ends'      => now()->addHours(6),
                        'description' => 'Now Testing Slot',
                        'active' => true,
                        'max'   => 10
                    ]);
                    PersonSlot::create([ 'person_id' => $person->id, 'slot_id' => $slot->id ]);
                    return $this->success();

                case 'pass':
                case 'fail':
                    $rows = PersonSlot::where('person_id', $person->id)
                            ->select('person_slot.*', 'slot.id as slot_id')
                            ->join('slot', 'slot.id', 'person_slot.slot_id')
                            ->where('position_id', Position::TRAINING)
                            ->whereYear('begins', $year)
                            ->get();

                    $pass = ($status == 'pass');
                    foreach ($rows as $r) {
                        $p = TraineeStatus::where('person_id', $person->id)
                            ->where('slot_id', $r->slot_id)
                            ->first();
                        if (!$p) {
                            TraineeStatus::create([ 'person_id' => $person->id, 'slot_id' => $r->slot_id, 'passed' => $pass]);
                        } else {
                            $p->update([ 'passed' => $pass ]);
                        }
                    }
                    return $this->success();

                case 'remove':
                    $rows = PersonSlot::where('person_id', $person->id)
                        ->join('slot', 'slot.id', 'person_slot.slot_id')
                        ->where('position_id', Position::TRAINING)
                        ->whereYear('begins', $year)
                        ->delete();
                    return $this->success();
                default:
                    throw new \InvalidArgumentException("Unknown training command");
            }
        }


        $status = request()->input('alpha');

        if (!empty($status)) {
            switch ($status) {
                case 'signup':
                    $slot = Slot::create([
                        'position_id'   => Position::ALPHA,
                        'begins'    => now()->addHours(24),
                        'ends'      => now()->addHours(25),
                        'description' => 'Alpha Test Slot',
                        'active' => true,
                        'max'   => 10
                    ]);
                    PersonSlot::create([ 'person_id' => $person->id, 'slot_id' => $slot->id ]);
                    break;
                case 'signup-past':
                    $slot = Slot::create([
                        'position_id'   => Position::ALPHA,
                        'begins'    => now()->subHours(25),
                        'ends'      => now()->subHours(24),
                        'description' => 'Past Alpha Test Slot',
                        'active' => true,
                        'max'   => 10
                    ]);
                    PersonSlot::create([ 'person_id' => $person->id, 'slot_id' => $slot->id ]);
                    break;
                case 'pass':
                    // Convert to Active
                    $person->changeStatus('active', $person->status, 'onboard debugging');
                    $person->save();
                    break;

                case 'bonk':
                    $person->changeStatus('bonked', $person->status, 'onboard debugging');
                    $person->save();
                    return $this->success();
                case 'remove':
                    $rows = PersonSlot::where('person_id', $person->id)
                        ->join('slot', 'slot.id', 'person_slot.slot_id')
                        ->where('position_id', Position::ALPHA)
                        ->whereYear('begins', $year)
                        ->delete();
                    break;
                default:
                    throw new \InvalidArgumentException("Unknown alpha command [$status]");
            }
            return $this->success();
        }

        $action = request()->input('action');
        if (!empty($action)) {
            switch ($action) {
                case 'remove-trainings':
                    $rows = Slot::whereYear('begins', $year)
                        ->where('position_id', Position::TRAINING)
                        ->get();
                    foreach ($rows as $r) {
                        \App\Models\TraineeStatus::where('slot_id', $r->id)->delete();
                        $r->delete();
                    }
                    break;

                case 'remove-alphas':
                    Slot::whereYear('begins', $year)
                        ->where('position_id', Position::ALPHA)
                        ->delete();
                    break;
                default:
                    throw new \InvalidArgumentException("Unknown training command");
            }

            return $this->success();
        }

        throw new \InvalidArgumentException("Unknown onboard debugging command");
    }

    /**
     * Retrieve status history
     */

    public function statusHistory(Person $person)
    {
        $this->authorize('statusHistory', Person::class);

        return response()->json([ 'history' => PersonStatus::retrieveAllForId($person->id) ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Exceptions\MoodleConnectFailureException;
use App\Exceptions\MoodleDownForMaintenanceException;
use App\Lib\Moodle;
use App\Mail\OnlineCourseEnrollmentMail;
use App\Mail\OnlineCourseResetPasswordMail;
use App\Models\ActionLog;
use App\Models\OnlineCourse;
use App\Models\Person;
use App\Models\PersonOnlineCourse;
use App\Models\Position;
use App\Models\Timesheet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Exceptions\UnacceptableConditionException;

class PersonOnlineCourseController extends ApiController
{
    /**
     * Create an online course account (if none exists)
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function setupPerson(Person $person): JsonResponse
    {
        $this->authorize('setupPerson', [PersonOnlineCourse::class, $person]);

        prevent_if_ghd_server('Online Course setup');

        $params = request()->validate([
            'position_id' => 'required|integer',
        ]);

        $positionId = $params['position_id'];
        $year = current_year();
        $course = OnlineCourse::findForPositionYear($positionId, $year, OnlineCourse::COURSE_FOR_ALL);

        if (!$course && $positionId == Position::TRAINING) {
            /*
             * When there's no online course defined for everyone, it's assumed two other online courses
             * are defined - one for returning active Rangers with 2 years or more experience, and one for
             * everyone else - Applicants, Binaries (less than 2 years experience.), Inactive Rangers, and Retirees.
             */

            if ($person->status == Person::ACTIVE && count(Timesheet::findYears($person->id, Timesheet::YEARS_RANGERED)) >= 2) {
                $for = OnlineCourse::COURSE_FOR_RETURNING;
            } else {
                $for = OnlineCourse::COURSE_FOR_NEW;
            }
            $course = OnlineCourse::findForPositionYear($positionId, $year, $for);
        }

        if (!$course) {
            throw new UnacceptableConditionException('Cannot find an appropriate course');
        }

        $password = null;
        $exists = true;
        $lms = null;

        try {
            if (empty($person->lms_id)) {
                // See if the person already has an online account setup
                $lms = new Moodle();
                if (!$lms->findPerson($person)) {
                    // Nope, create the user
                    if (!$lms->createUser($person, $password)) {
                        // Crap, failed.
                        return response()->json(['status' => 'fail']);
                    }
                    $exists = false;
                }
            }

            $poc = PersonOnlineCourse::firstOrNewForPersonYear($person->id, $year, $positionId);

            if (!$poc->exists || $poc->course_id != $course->id) {
                if (!$lms) {
                    $lms = new Moodle;
                }

                if ($exists) {
                    // Sync the existing user's info when enrolling a new course.
                    // Assumes - this is the first enrollment for the event cycle.
                    $lms->syncPersonInfo($person);
                    $person->auditReason = 'moodle user info sync';
                    $person->saveWithoutValidation();
                    ActionLog::record($person, 'lms-sync-user', 'course enrollment sync');
                }

                // Enroll the person in the course
                $lms->enrollPerson($person, $poc, $course);
            }
        } catch (MoodleDownForMaintenanceException $e) {
            return response()->json(['status' => 'down-for-maintenance']);
        } catch (MoodleConnectFailureException $e) {
            return response()->json(['status' => 'down-for-maintenance']);
        }

        if (!$exists) {
            mail_send(new OnlineCourseEnrollmentMail($person, $course, $password));
        }

        return response()->json([
            'status' => $exists ? 'exists' : 'created',
            'username' => $person->lms_username,
            'password' => $password,
            'course_for' => $course->course_for,
        ]);
    }

    /**
     * Reset the password for a moodle account.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException|MoodleDownForMaintenanceException
     */

    public function resetPassword(Person $person): JsonResponse
    {
        $this->authorize('resetPassword', [PersonOnlineCourse::class, $person]);

        if (empty($person->lms_id)) {
            return response()->json(['status' => 'no-account']);
        }

        $lms = new Moodle();
        $password = null;
        $lms->resetPassword($person, $password);

        mail_send(new OnlineCourseResetPasswordMail($person, $password), false);
        ActionLog::record($person, 'lms-password-reset', 'password reset requested');

        return response()->json(['status' => 'success', 'password' => $password]);
    }


    /**
     * Mark a person as having completed the online course.
     * (Very dangerous, only use this superpower for good.)
     *
     * @throws AuthorizationException|ValidationException
     */

    public function markCompleted(Person $person): JsonResponse
    {
        $this->authorize('markCompleted', PersonOnlineCourse::class);
        prevent_if_ghd_server('Mark online course as completed');

        $personId = $person->id;
        $params = request()->validate([
            'year' => 'required|integer',
            'position_id' => 'required|integer|exists:position,id'
        ]);

        $positionId = $params['position_id'];
        $year = $params['year'];

        if (PersonOnlineCourse::didCompleteForYear($personId, $year, $positionId)) {
            return throw new UnacceptableConditionException("Person has already completed online course for $year");
        }

        $poc = PersonOnlineCourse::firstOrNewForPersonYear($person->id, $year, $positionId);
        $poc->completed_at = now();
        $poc->auditReason = 'force marked completed';
        if (!$poc->save()) {
            return $this->restError($poc);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Obtain the user information from the Online Course
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException|MoodleDownForMaintenanceException
     */

    public function getInfo(Person $person): Jsonresponse
    {
        $this->authorize('getInfo', [PersonOnlineCourse::class, $person]);

        if (empty($person->lms_id)) {
            return response()->json(['status' => 'not-setup']);
        }

        $user = (new Moodle())->findPersonByMoodleId($person->lms_id);

        if (!$user) {
            return response()->json(['status' => 'missing-account', 'lms_id' => $person->lms_id]);
        }

        $userInfo = [
            'idnumber' => $user->idnumber,
            'username' => $user->username,
            'first_name' => $user->firstname,
            'last_name' => $user->lastname,
            'email' => $user->email,
        ];

        $inSync = true;
        if ($user->idnumber != $person->id) {
            $userInfo['username_expected'] = $person->id;
            $inSync = false;
        }

        $username = Moodle::buildMoodleUsername($person);
        if ($username != $userInfo['username']) {
            $userInfo['username_expected'] = $username;
            $inSync = false;
        }

        $firstName = $person->desired_first_name();
        if ($user->firstname != $firstName) {
            $userInfo['first_name_expected'] = $firstName;
            $inSync = false;
        }

        if ($user->lastname != $person->last_name) {
            $userInfo['last_name_expected'] = $person->last_name;
            $inSync = false;
        }

        $email = Moodle::normalizeEmail($person->email);
        if ($user->email != $email) {
            $userInfo['email_expected'] = $email;
            $inSync = false;
        }

        return response()->json(['status' => 'success', 'user' => $userInfo, 'in_sync' => $inSync]);
    }

    /**
     * Sync the Moodle account with the Clubhouse
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws MoodleDownForMaintenanceException
     */

    public function syncInfo(Person $person): JsonResponse
    {
        $this->authorize('syncInfo', [PersonOnlineCourse::class, $person]);

        if (empty($person->lms_id)) {
            return response()->json(['status' => 'not-setup']);
        }

        (new Moodle())->syncPersonInfo($person);
        return response()->json(['status' => 'success']);
    }

    /**
     * Change the course for a person
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function change(Person $person): JsonResponse
    {
        $params = request()->validate([
            'online_course_id' => 'required|integer|exists:online_course,id',
            'position_id' => 'required|integer|exists:position,id',
            'year' => 'required|integer',
        ]);

        $onlineCourse = OnlineCourse::findOrFail($params['online_course_id']);
        $year = $params['year'];
        $positionId = $params['position_id'];

        $this->authorize('change', [PersonOnlineCourse::class, $person, $onlineCourse]);

        if ($onlineCourse->position_id != $positionId) {
            throw new UnacceptableConditionException('Online Course and position do not match');
        }

        $poc = PersonOnlineCourse::firstOrNewForPersonYear($person->id, $year, $positionId);
        $poc->online_course_id = $onlineCourse->id;
        $poc->saveWithoutValidation();
        return $this->success();
    }

    /**
     * Retrieve the Online Course Info for a given year and position.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function courseInfo(Person $person): JsonResponse
    {
        $this->authorize('courseInfo', [PersonOnlineCourse::class, $person]);
        $params = request()->validate([
            'position_id' => 'required|integer|exists:position,id',
            'year' => 'required|integer'
        ]);

        $poc = PersonOnlineCourse::findForPersonYear($person->id, $params['year'], $params['position_id']);
        if ($poc) {
            return response()->json(['online_course' => [
                'id' => $poc->online_course_id,
                // online_course may be null if viewing a manual-review year (prior to 2020).
                'course_id' => $poc->online_course?->course_id,
                'enrolled_at' => (string)$poc->enrolled_at,
                'completed_at' => (string)$poc->completed_at,
            ]]);
        }

        return response()->json(['online_course' => null]);
    }
}

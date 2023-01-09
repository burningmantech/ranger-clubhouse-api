<?php

namespace App\Http\Controllers;

use App\Exceptions\MoodleDownForMaintenanceException;
use App\Lib\Moodle;
use App\Mail\OnlineTrainingEnrollmentMail;
use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\PersonOnlineTraining;
use App\Models\Setting;
use App\Models\Timesheet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class OnlineTrainingController extends ApiController
{
    /**
     * Return a list of people who have completed online training.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function index(): JsonResponse
    {
        $query = request()->validate([
            'year' => 'required|integer',
            'person_id' => 'sometimes|integer'
        ]);

        $this->authorize('view', PersonOnlineTraining::class);
        $rows = PersonOnlineTraining::findForQuery($query);
        return $this->success($rows, null, 'person_ot');
    }

    /**
     * Create an online training account (if none exists)
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function setupPerson(Person $person): JsonResponse
    {
        $this->authorize('setupPerson', [PersonOnlineTraining::class, $person]);

        prevent_if_ghd_server('Online Course setup');

        $pe = PersonEvent::firstOrNewForPersonYear($person->id, current_year());

        /*
         * Any active Ranger with 2 or more years experience gets to take the half course.
         * Everyone else (PNVs, Auditors, Binaries, Inactives, etc) take the full course.
         */

        if ($person->status == Person::ACTIVE
            && !setting('OnlineTrainingFullCourseForVets')
            && count(Timesheet::findYears($person->id, Timesheet::YEARS_RANGERED)) >= 2) {
            $courseId = setting('MoodleHalfCourseId');
            $type = 'half';
        } else {
            $courseId = setting('MoodleFullCourseId');
            $type = 'full';
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

            if ($pe->lms_course_id != $courseId) {
                if (!$lms) {
                    $lms = new Moodle;
                }

                // Enroll the person in the course
                $lms->enrollPerson($person, $pe, $courseId);
            }
        } catch (MoodleDownForMaintenanceException $e) {
            return response()->json([ 'status' => 'down-for-maintenance' ]);
        }

        if (!$exists) {
            mail_to_person($person, new OnlineTrainingEnrollmentMail($person, $type, $password), true);
        }

        return response()->json([
            'status' => $exists ? 'exists' : 'created',
            'username' => $person->lms_username,
            'password' => $password,
            'course_type' => $type,
        ]);
    }

    /**
     * Attempt to scan the Moodle enrollments and associate any account that does not have a user id
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function linkUsers(): JsonResponse
    {
        $this->authorize('linkUsers', PersonOnlineTraining::class);
        prevent_if_ghd_server('Online Course admin');

        $fullId = setting('MoodleFullCourseId');
        $halfId = setting('MoodleHalfCourseId');

        $lms = new Moodle();

        return response()->json([
            'full_course' => !empty($fullId) ? $lms->linkUsersInCourse($fullId) : [],
            'half_course' => !empty($halfId) ? $lms->linkUsersInCourse($halfId) : [],
        ]);
    }

    /**
     * Obtain the online training configuration
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function config(): JsonResponse
    {
        $this->authorize('config', PersonOnlineTraining::class);

        $otSettings = setting([
            'OnlineTrainingDisabledAllowSignups',
            'OnlineTrainingEnabled',
            'OnlineTrainingFullCourseForVets',
            'MoodleFullCourseId',
            'MoodleHalfCourseId',
        ]);

        return response()->json($otSettings);
    }

    /**
     * Retrieve all available courses
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function courses(): JsonResponse
    {
        $this->authorize('courses', PersonOnlineTraining::class);

        prevent_if_ghd_server('Online Course admin');

        $lms = new Moodle();
        $fullCourse = setting('MoodleFullCourseId');
        $halfCourse = setting('MoodleHalfCourseId');
        $courses = $lms->retrieveAvailableCourses();
        foreach ($courses as $course) {
            if ($course->id == $fullCourse) {
                $course->is_full_course = true;
            }
            if ($course->id == $halfCourse) {
                $course->is_half_course = true;
            }
        }
        return response()->json(['courses' => $courses]);
    }

    /**
     * Retrieve all available courses
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function setCourseType(): JsonResponse
    {
        $this->authorize('setCourseType', PersonOnlineTraining::class);

        prevent_if_ghd_server('Online Course admin');

        $params = request()->validate([
            'course_id' => 'integer|required',
            'type' => 'string|required'
        ]);

        $setting = Setting::findOrFail($params['type'] == 'full' ? 'MoodleFullCourseId' : 'MoodleHalfCourseId');
        $setting->value = (string)$params['course_id'];
        $setting->auditReason = "online course update";
        if (!$setting->save()) {
            return $this->restError($setting);
        }
        Setting::kickQueues();
        return $this->success();
    }

    /**
     * Retrieve everyone who is enrolled.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function enrollment(): JsonResponse
    {
        $this->authorize('enrollment', PersonOnlineTraining::class);
        prevent_if_ghd_server('Online Course admin');

        $lms = new Moodle();

        $fullId = setting('MoodleFullCourseId');
        $halfId = setting('MoodleHalfCourseId');

        return response()->json([
            'full_course' => !empty($fullId) ? $lms->retrieveCourseEnrollmentWithCompletion($fullId) : [],
            'half_course' => !empty($halfId) ? $lms->retrieveCourseEnrollmentWithCompletion($halfId) : [],
        ]);
    }

    /**
     * Mark a person as having completed the online course.
     * (Very dangerous, only use this superpower for good.)
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function markCompleted(Person $person): JsonResponse
    {
        $this->authorize('markCompleted', PersonOnlineTraining::class);
        prevent_if_ghd_server('Mark online course as completed');

        $personId = $person->id;
        $year = current_year();

        if (PersonOnlineTraining::didCompleteForYear($personId, $year)) {
            return throw new InvalidArgumentException("Person has already completed online training for $year");
        }

        $ot = new PersonOnlineTraining([
            'person_id' => $personId,
            'completed_at' => now(),
            'type' => PersonOnlineTraining::MOODLE
        ]);

        $ot->auditReason = 'force marked completed';
        if (!$ot->save()) {
            return $this->restError($ot);
        }

        return response()->json(['status' => 'success']);
    }
}

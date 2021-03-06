<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;

use App\Lib\Moodle;

use App\Mail\OnlineTrainingEnrollmentMail;

use App\Models\Person;
use App\Models\PersonOnlineTraining;
use App\Models\Timesheet;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class OnlineTrainingController extends ApiController
{
    /**
     * Return a list of people who have completed online training.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function index()
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
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function setupPerson(Person $person)
    {
        $this->authorize('setupPerson', [PersonOnlineTraining::class, $person]);

        /*
         * Any active Ranger with 2 or more years experience gets to take the half course.
         * Everyone else (PNVs, Auditors, Binaries, Inactives, etc) take the full course.
         */
        if ($person->status == Person::ACTIVE
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

        if (empty($person->lms_id)) {
            // See if the person already has an online account setup
            $lms = new Moodle();
            if ($lms->findPerson($person) == false) {
                // Nope, create the user
                if ($lms->createUser($person, $password) == false) {
                    // Crap, failed.
                    return response()->json(['status' => 'fail']);
                }
                $exists = false;
            }
        }

        if ($person->lms_course != $courseId) {
            if (!$lms) {
                $lms = new Moodle;
            }

            // Enroll the person in the course
            $lms->enrollPerson($person, $courseId);
        }

        if (!$exists) {
            mail_to($person->email, new OnlineTrainingEnrollmentMail($person, $type, $password), true);
        }

        return response()->json([
            'status' => $exists ? 'exists' : 'created',
            'password' => $password,
            'course_type' => $type,
            'expiry_date' => (string) $person->lms_course_expiry,
        ]);
    }

    /**
     * Obtain the online training configuration
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function config()
    {
        $this->authorize('config', PersonOnlineTraining::class);

        $otSettings = setting([
            'OnlineTrainingDisabledAllowSignups',
            'OnlineTrainingEnabled',
            'LMSHalfCourseId',
            'LMSFullCourseId'
        ]);

        return response()->json($otSettings);
    }

    /**
     * Retrieve all available courses
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function courses()
    {
        $this->authorize('courses', PersonOnlineTraining::class);

        $lms = new LMS();
        return response()->json(['courses' => $lms->retrieveAvailableCourses()]);
    }

    /**
     * Retrieve everyone who is enrolled.
     *
     * @throws AuthorizationException
     */

    public function enrollment()
    {
        $this->authorize('enrollment', PersonOnlineTraining::class);
        $lms = new LMS();

        $fullId = setting('LMSFullCourseId');
        $halfId = setting('LMSHalfCourseId');

        return response()->json([
            'full_course' => !empty($fullId) ? $lms->retrieveCourseEnrollment($fullId) : [],
            'half_course' => !empty($halfId) ? $lms->retrieveCourseEnrollment($halfId): [],
        ]);
    }
}

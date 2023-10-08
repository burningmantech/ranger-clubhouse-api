<?php

namespace App\Http\Controllers;

use App\Exceptions\MoodleDownForMaintenanceException;
use App\Lib\Moodle;
use App\Lib\Reports\OnlineCourseReport;
use App\Models\OnlineCourse;
use App\Models\PersonOnlineCourse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class OnlineCourseController extends ApiController
{
    /**
     * Display a listing of the online courses.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $this->authorize('index', [OnlineCourse::class]);
        $params = request()->validate([
            'year' => 'required|integer',
            'position_id' => 'sometimes|integer|exists:position,id'
        ]);

        return $this->success(OnlineCourse::findForQuery($params), null, 'online_course');
    }

    /***
     * Store a newly created online course
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', [OnlineCourse::class]);
        $onlineCourse = new OnlineCourse();
        $this->fromRest($onlineCourse);

        if ($onlineCourse->save()) {
            return $this->success($onlineCourse);
        }

        return $this->restError($onlineCourse);
    }

    /**
     * Display the specified online course.
     * @param OnlineCourse $onlineCourse
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(OnlineCourse $onlineCourse): JsonResponse
    {
        $this->authorize('show', $onlineCourse);
        return $this->success($onlineCourse);
    }

    /**
     * Update the specified online course
     *
     * @param OnlineCourse $onlineCourse
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws ValidationException
     */

    public function update(OnlineCourse $onlineCourse): JsonResponse
    {
        $this->authorize('update', $onlineCourse);
        $this->fromRest($onlineCourse);

        if ($onlineCourse->save()) {
            return $this->success($onlineCourse);
        }

        return $this->restError($onlineCourse);
    }

    /**
     * @param OnlineCourse $onlineCourse
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(OnlineCourse $onlineCourse): JsonResponse
    {
        $this->authorize('destroy', $onlineCourse);
        $onlineCourse->delete();
        return $this->restDeleteSuccess();
    }

    /**
     * sync the course name from the LMS.
     *
     * @param OnlineCourse $onlineCourse
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws MoodleDownForMaintenanceException
     */

    public function setName(OnlineCourse $onlineCourse): JsonResponse
    {
        $this->authorize('setName', $onlineCourse);
        $result = (new Moodle())->retrieveCourseInfo($onlineCourse->course_id);
        if (!$result) {
            return $this->restError('Could not obtain the course information.');
        }
        $onlineCourse->name = $result->fullname;
        $onlineCourse->saveWithoutValidation();

        return $this->success($onlineCourse);
    }

    /**
     * Run the progress report
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function progressReport(): JsonResponse
    {
        $query = request()->validate([
            'year' => 'required|integer',
            'position_id' => 'required|integer',
        ]);

        $this->authorize('progressReport', OnlineCourse::class);

        return response()->json(['people' => OnlineCourseReport::execute($query['year'], $query['position_id'])]);
    }

    /**
     * Retrieve all available courses
     *
     * @return JsonResponse
     * @throws AuthorizationException|MoodleDownForMaintenanceException
     */

    public function courses(): JsonResponse
    {
        $this->authorize('courses', OnlineCourse::class);

        prevent_if_ghd_server('Online Course admin');

        $lms = new Moodle();
        $coursesById = OnlineCourse::with('position:id,title')->get()->groupBy('course_id');
        $availableCourses = $lms->retrieveAvailableCourses();
        foreach ($availableCourses as $course) {
            $ocs = $coursesById->get($course->id);
            if ($ocs) {
                $courses = $ocs->map(fn($c) => ['title' => $c->position->title, 'course_for' => $c->course_for, 'year' => $c->year ])->toArray();
            } else {
                $courses = [];
            }
            $course->online_courses = $courses;
        }

        return response()->json(['courses' => $availableCourses]);
    }

    /**
     * Obtain the online course configuration
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function config(): JsonResponse
    {
        $this->authorize('config', PersonOnlineCourse::class);

        $ocSettings = setting([
            'OnlineCourseDisabledAllowSignups',
            'OnlineCourseEnabled',
        ]);

        return response()->json($ocSettings);
    }

    /**
     * Retrieve everyone who is enrolled.
     *
     * @param OnlineCourse $onlineCourse
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws MoodleDownForMaintenanceException
     */

    public function enrollment(OnlineCourse $onlineCourse): JsonResponse
    {
        $this->authorize('enrollment', $onlineCourse);
        prevent_if_ghd_server('Online Course admin');

        $lms = new Moodle;
        return response()->json(['people' => $lms->retrieveCourseEnrollmentWithCompletion($onlineCourse->course_id)]);
    }

    /**
     * Attempt to scan the Moodle enrollments and associate any account that does not have a user id
     *
     * @return JsonResponse
     * @throws AuthorizationException|MoodleDownForMaintenanceException
     */

    public function linkUsers(): JsonResponse
    {
        $this->authorize('linkUsers', PersonOnlineCourse::class);
        prevent_if_ghd_server('Online Course admin');

        $courses = OnlineCourse::findForQuery(['year' => current_year()]);

        $lms = new Moodle();

        $results = [];
        foreach ($courses as $course) {
            $results[] = [
                'course_id' => $course->course_id,
                'title' => $course->position->title,
                'users' => $lms->linkUsersInCourse($course->course_id)
            ];
        }
        return response()->json(['courses' => $results]);
    }
}

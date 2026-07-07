<?php

namespace Tests\Feature;

use App\Exceptions\MoodleConnectFailureException;
use App\Exceptions\MoodleDownForMaintenanceException;
use App\Lib\Moodle;
use App\Models\ErrorLog;
use App\Models\OnlineCourse;
use App\Models\Person;
use App\Models\PersonOnlineCourse;
use App\Models\Position;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MoodleTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        // The Moodle constructor reads these (throwing if absent).
        $this->setting('MoodleDomain', 'https://moodle.test');
        $this->setting('MoodleToken', 'test-token');
        $this->setting('MoodleServiceName', 'clubhouse');
        $this->setting('MoodleStudentRoleID', '5');
    }

    /**
     * Http::fake() is the seam: Moodle's web-service calls run without a live
     * Moodle server, so the decode-and-filter behavior is exercisable.
     *
     * @return void
     */

    public function test_retrieve_available_courses_decodes_and_filters_site(): void
    {
        Http::fake(['*' => Http::response([
            ['id' => 1, 'format' => 'topics', 'fullname' => 'Online Training'],
            ['id' => 2, 'format' => 'site', 'fullname' => 'Site home'],
        ])]);

        $courses = (new Moodle())->retrieveAvailableCourses();

        $this->assertCount(1, $courses);
        $this->assertEquals(1, $courses[0]->id);
    }

    /**
     * A maintenance response surfaces as a typed exception — a failure path that
     * needs no live server to exercise.
     *
     * @return void
     */

    public function test_maintenance_response_throws(): void
    {
        Http::fake(['*' => Http::response('Site undergoing maintenance', 503)]);

        $this->expectException(MoodleDownForMaintenanceException::class);
        (new Moodle())->retrieveAvailableCourses();
    }

    /**
     * A failed (non-maintenance) request logs an ErrorLog record, but the raw
     * wstoken must never appear anywhere in the persisted data payload.
     *
     * @return void
     */

    public function test_failed_request_does_not_log_raw_wstoken(): void
    {
        Http::fake(['*' => Http::response('Site is broken', 500)]);

        try {
            (new Moodle())->retrieveAvailableCourses();
        } catch (RuntimeException) {
            // Expected - the failure path is what we're inspecting.
        }

        $logs = ErrorLog::query()->where('error_type', 'lms-request-failure')->get();
        $this->assertGreaterThan(0, $logs->count());

        foreach ($logs as $log) {
            $this->assertStringNotContainsString('test-token', $log->data);
        }
    }

    /**
     * A connection failure logs an ErrorLog record, but the raw wstoken must
     * never appear anywhere in the persisted data payload.
     *
     * @return void
     */

    public function test_connection_failure_does_not_log_raw_wstoken(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        try {
            (new Moodle())->retrieveAvailableCourses();
        } catch (MoodleConnectFailureException) {
            // Expected - the failure path is what we're inspecting.
        }

        $logs = ErrorLog::query()->where('error_type', 'moodle-connect-failure')->get();
        $this->assertGreaterThan(0, $logs->count());

        foreach ($logs as $log) {
            $this->assertStringNotContainsString('test-token', $log->data);
            $this->assertStringContainsString('REDACTED', $log->data);
        }
    }

    /**
     * generatePassword() must use a cryptographically secure random source and
     * a wider unpredictable portion than the old 3-digit rand() based scheme.
     *
     * @return void
     */

    public function test_generate_password_has_widened_random_component(): void
    {
        $person = new Person();
        $person->first_name = 'Alice';
        $person->last_name = 'Smith';

        $password = Moodle::generatePassword($person);

        $this->assertMatchesRegularExpression('/^SmithA!\d{6}/', $password);
        $this->assertGreaterThanOrEqual(13, strlen($password));

        // Repeated calls should not be reproducible/predictable the way a
        // fixed mt_rand()/rand() seed would make them.
        mt_srand(1234);
        $first = Moodle::generatePassword($person);
        mt_srand(1234);
        $second = Moodle::generatePassword($person);

        $this->assertNotEquals($first, $second);
    }

    /**
     * findClubhouseUsers() must only match the roster's idnumbers, and must not
     * be influenced by (or return) unrelated person rows present in the table.
     *
     * @return void
     */

    public function test_find_clubhouse_users_only_matches_roster_ids(): void
    {
        $wanted = Person::factory()->create(['callsign' => 'Wanted One']);
        Person::factory()->create(['callsign' => 'Unrelated Person']);
        Person::factory()->create(['callsign' => 'Another Unrelated Person']);

        $roster = [
            (object)['id' => 501, 'idnumber' => (string)$wanted->id, 'email' => $wanted->email],
            (object)['id' => 502, 'idnumber' => '', 'email' => 'nobody@example.com'],
        ];

        $found = (new Moodle())->findClubhouseUsers($roster);

        $this->assertCount(1, $found);
        $this->assertSame($wanted->id, $found[0]->person->id);
        $this->assertSame($wanted->callsign, $found[0]->person->callsign);
    }

    /**
     * The usort comparator inside retrieveCourseEnrollmentWithCompletion() must be
     * antisymmetric: linked (->person) rows must sort consistently ahead of
     * unlinked rows, regardless of which side of the pair is checked first.
     * Exercised through the real method (via Http::fake and real Person rows)
     * rather than a re-implemented copy of the comparator, so a regression in
     * the production closure actually fails this test.
     *
     * @return void
     */

    public function test_enrollment_sort_orders_linked_rows_before_unlinked(): void
    {
        $zulu = Person::factory()->create(['callsign' => 'Zulu']);
        $alpha = Person::factory()->create(['callsign' => 'Alpha']);

        // Linked/unlinked/linked input order is deliberate: PHP's sort only
        // compares adjacent-ish elements, so an unlinked-then-linked-then-linked
        // input happens to sort "correctly" even with a broken (non-antisymmetric)
        // comparator. This ordering reliably exposes that regression instead.
        $roster = [
            (object)['id' => 601, 'idnumber' => (string)$alpha->id, 'email' => $alpha->email, 'roles' => [(object)['roleid' => 99]]],
            (object)['id' => 602, 'idnumber' => '', 'email' => 'unlinked@example.com', 'roles' => [(object)['roleid' => 99]]],
            (object)['id' => 603, 'idnumber' => (string)$zulu->id, 'email' => $zulu->email, 'roles' => [(object)['roleid' => 99]]],
        ];

        Http::fake(['*' => Http::response($roster)]);

        $students = array_values((new Moodle())->retrieveCourseEnrollmentWithCompletion(1));

        $this->assertCount(3, $students);
        $this->assertTrue(isset($students[0]->person));
        $this->assertTrue(isset($students[1]->person));
        $this->assertFalse(isset($students[2]->person));
        $this->assertSame('Alpha', $students[0]->person->callsign);
        $this->assertSame('Zulu', $students[1]->person->callsign);
        $this->assertSame('unlinked@example.com', $students[2]->email);
    }

    /**
     * retrieveCourseEnrollmentWithCompletion() relies on the extracted
     * isStudentRole()/latestCompletionTimestamp() helpers: a non-student role
     * must be flagged as a teacher and skip completion lookup entirely, while
     * a student's completed_at must reflect the max timecompleted across
     * multiple completions rows.
     *
     * @return void
     */

    public function test_enrollment_with_completion_uses_student_role_and_latest_completion_helpers(): void
    {
        $studentRoleId = 5;
        $roster = [
            (object)[
                'id' => 1,
                'idnumber' => '',
                'email' => 'teacher@example.com',
                'roles' => [(object)['roleid' => 99]],
            ],
            (object)[
                'id' => 2,
                'idnumber' => '',
                'email' => 'student@example.com',
                'roles' => [(object)['roleid' => $studentRoleId]],
            ],
        ];

        Http::fake(function ($request) use ($roster) {
            if (str_contains($request->url(), Moodle::WS_ENROLLED_USERS)) {
                return Http::response($roster);
            }

            if (str_contains($request->url(), Moodle::WS_COURSE_COMPLETION)) {
                return Http::response([
                    'completionstatus' => [
                        'completed' => true,
                        'completions' => [
                            ['timecompleted' => 1000],
                            ['timecompleted' => 5000],
                            ['timecompleted' => 2000],
                        ],
                    ],
                ]);
            }

            return Http::response([]);
        });

        $students = (new Moodle())->retrieveCourseEnrollmentWithCompletion(1);

        $teacher = current(array_filter($students, fn($s) => $s->id === 1));
        $student = current(array_filter($students, fn($s) => $s->id === 2));

        $this->assertTrue($teacher->is_teacher);
        $this->assertFalse(isset($teacher->completed_at));

        $this->assertFalse(isset($student->is_teacher));
        $this->assertSame(
            (string)Carbon::createFromTimestamp(5000)->tz('America/Phoenix'),
            $student->completed_at
        );
    }

    /**
     * linkUsersInCourse() must pre-fetch its Person lookups in bulk rather than
     * issuing per-student queries, so the query count should stay flat as the
     * roster grows instead of scaling linearly.
     *
     * @return void
     */

    public function test_link_users_in_course_does_not_issue_per_student_queries(): void
    {
        $linkedByIdnumber = Person::factory()->create();
        $linkedByIdnumber->lms_id = 9001;
        $linkedByIdnumber->lms_username = 'linkedbyidnumber';
        $linkedByIdnumber->auditReason = 'test setup';
        $linkedByIdnumber->saveWithoutValidation();

        $linkedByEmail = Person::factory()->create();

        $roster = [
            (object)[
                'id' => 9001,
                'idnumber' => (string)$linkedByIdnumber->id,
                'username' => 'linkedbyidnumber',
                'email' => $linkedByIdnumber->email,
            ],
            (object)[
                'id' => 9002,
                'idnumber' => '',
                'username' => 'linkedbyemail',
                'email' => $linkedByEmail->email,
            ],
            (object)[
                'id' => 9003,
                'idnumber' => '',
                'username' => 'nomatch',
                'email' => 'nobody-matches@example.com',
            ],
        ];

        Http::fake(function ($request) use ($roster) {
            if (str_contains($request->url(), Moodle::WS_ENROLLED_USERS)) {
                return Http::response($roster);
            }

            return Http::response(['id' => 9002]);
        });

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        [$updated, $notFound] = (new Moodle())->linkUsersInCourse(1);

        // Two bulk person lookups (by id, by email) regardless of roster size,
        // plus the per-row update queries only for rows that actually changed
        // (one student here: 9002, linked by email). This must not scale
        // linearly with the roster size (3 students but nowhere near 2*3
        // lookup queries).
        $this->assertLessThan(count($roster) * 2, $queryCount);

        $this->assertCount(1, $updated);
        $this->assertSame(9002, $updated[0]->id);
        $this->assertSame($linkedByEmail->id, $updated[0]->idnumber);

        $this->assertCount(1, $notFound);
        $this->assertSame(9003, $notFound[0]->id);
    }

    /**
     * requestWebService() must set an explicit overall timeout (not just a
     * connect timeout), so a slow-but-connected Moodle response cannot hang
     * the request indefinitely.
     *
     * @return void
     */

    public function test_request_web_service_sets_overall_timeout(): void
    {
        $seenOptions = null;

        Http::fake(function ($request, $options) use (&$seenOptions) {
            $seenOptions = $options;
            return Http::response([]);
        });

        (new Moodle())->retrieveAvailableCourses();

        $this->assertSame(10, $seenOptions['connect_timeout']);
        $this->assertSame(30, $seenOptions['timeout']);
    }

    /**
     * A Moodle write response with a non-empty top-level "warnings" array means
     * the enrollment was actually rejected server-side, even without a top-level
     * "exception" key. enrollPerson() must throw and must not commit the local
     * PersonOnlineCourse state in that case.
     *
     * @return void
     */

    public function test_enroll_person_throws_and_does_not_commit_on_warnings(): void
    {
        Http::fake(['*' => Http::response([
            'warnings' => [
                ['item' => 'user', 'itemid' => 1, 'warningcode' => '1', 'message' => 'User already enrolled'],
            ],
        ])]);

        $person = Person::factory()->create(['lms_id' => 123]);
        $course = new OnlineCourse();
        $course->id = 55;
        $course->course_id = 999;

        $poc = new PersonOnlineCourse();
        $poc->person_id = $person->id;
        $poc->position_id = 1;
        $poc->year = 2026;
        $poc->online_course_id = 1;
        $poc->auditReason = 'test setup';
        $poc->saveWithoutValidation();

        $this->expectException(RuntimeException::class);

        try {
            (new Moodle())->enrollPerson($person, $poc, $course);
        } finally {
            $this->assertSame(1, $poc->fresh()->online_course_id);
            $this->assertNull($poc->fresh()->enrolled_at);

            $logs = ErrorLog::query()->where('error_type', 'lms-request-warning')->get();
            $this->assertGreaterThan(0, $logs->count());
        }
    }

    /**
     * A Moodle "update user" response with a non-empty top-level "warnings" array
     * means the update was rejected server-side. updateUser() (via
     * syncPersonInfo()) must throw and log rather than silently succeeding.
     *
     * @return void
     */

    public function test_update_user_throws_on_warnings(): void
    {
        Http::fake(['*' => Http::response([
            'warnings' => [
                ['item' => 'user', 'itemid' => 1, 'warningcode' => '1', 'message' => 'Invalid user'],
            ],
        ])]);

        $person = Person::factory()->create(['lms_id' => 123]);

        $this->expectException(RuntimeException::class);

        try {
            (new Moodle())->syncPersonInfo($person);
        } finally {
            $logs = ErrorLog::query()->where('error_type', 'lms-request-warning')->get();
            $this->assertGreaterThan(0, $logs->count());
        }
    }

    /**
     * createUser() must not blindly read $result[0]->id. An empty/malformed
     * create response (no top-level exception, but no created user either)
     * must throw instead of persisting a bad lms_id.
     *
     * @return void
     */

    public function test_create_user_throws_on_malformed_response(): void
    {
        Http::fake(['*' => Http::response([])]);

        $person = Person::factory()->create(['lms_id' => null]);
        $password = null;

        $this->expectException(RuntimeException::class);

        try {
            (new Moodle())->createUser($person, $password);
        } finally {
            $this->assertNull($person->fresh()->lms_id);

            $logs = ErrorLog::query()->where('error_type', 'lms-request-failure')->get();
            $this->assertGreaterThan(0, $logs->count());
        }
    }

    /**
     * findPersonByEmail() must tolerate a decoded response missing the "users"
     * property rather than throwing an uncaught Error.
     *
     * @return void
     */

    public function test_find_person_by_email_handles_missing_users_property(): void
    {
        Http::fake(['*' => Http::response(['somethingelse' => true])]);

        $result = (new Moodle())->findPersonByEmail('nobody@example.com');

        $this->assertSame([], $result);
    }

    /**
     * findPersonByMoodleId() must tolerate a decoded response missing the
     * "users" property rather than throwing an uncaught Error.
     *
     * @return void
     */

    public function test_find_person_by_moodle_id_handles_missing_users_property(): void
    {
        Http::fake(['*' => Http::response(['somethingelse' => true])]);

        $result = (new Moodle())->findPersonByMoodleId(123);

        $this->assertNull($result);
    }

    /**
     * ClubhouseMoodleCompletion::handle() must not abort the whole run when a
     * single course's processCourseCompletion() throws a plain RuntimeException
     * (e.g. a malformed/erroring Moodle response for that one course) -- the
     * remaining courses still need to be scanned.
     *
     * @return void
     */

    public function test_command_continues_scanning_after_runtime_exception_on_one_course(): void
    {
        $this->setting('OnlineCourseEnabled', true);

        $this->createOnlineCourse('111');
        $this->createOnlineCourse('222');

        $requestedCourseIds = [];
        Http::fake(function ($request) use (&$requestedCourseIds) {
            if (str_contains($request->url(), Moodle::WS_ENROLLED_USERS)) {
                parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);
                $requestedCourseIds[] = $query['courseid'];

                if ($query['courseid'] === '111') {
                    // Non-maintenance failure for this one course only, causing
                    // decodeResponse() to throw a plain RuntimeException.
                    return Http::response('Server error', 500);
                }

                return Http::response([]);
            }

            return Http::response([]);
        });

        Artisan::call('clubhouse:moodle-completion');

        $this->assertEqualsCanonicalizing(['111', '222'], $requestedCourseIds);

        $logs = ErrorLog::query()->where('error_type', 'moodle-completion-course-failure')->get();
        $this->assertGreaterThan(0, $logs->count());
    }

    /**
     * ClubhouseMoodleCompletion::handle() must treat a MoodleConnectFailureException
     * as server-wide: it should stop scanning entirely rather than retrying (and
     * re-timing-out) for every remaining course.
     *
     * @return void
     */

    public function test_command_stops_scanning_remaining_courses_on_connect_failure(): void
    {
        $this->setting('OnlineCourseEnabled', true);

        $this->createOnlineCourse('111');
        $this->createOnlineCourse('222');

        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        Artisan::call('clubhouse:moodle-completion');

        $logs = ErrorLog::query()->where('error_type', 'moodle-connect-failure')->get();
        $this->assertCount(1, $logs);
    }

    /**
     * Build and persist an OnlineCourse for the current year, each with its own
     * Position so the unique (position_id, year, course_for) constraint is not
     * violated.
     *
     * @param string $courseId the LMS course id
     * @return OnlineCourse
     */

    private function createOnlineCourse(string $courseId): OnlineCourse
    {
        $course = new OnlineCourse();
        $course->name = "Course $courseId";
        $course->year = current_year();
        $course->position_id = Position::factory()->create()->id;
        $course->course_id = $courseId;
        $course->course_for = OnlineCourse::COURSE_FOR_ALL;
        $course->auditReason = 'test setup';
        $course->saveWithoutValidation();

        return $course;
    }
}

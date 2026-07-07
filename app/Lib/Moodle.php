<?php

namespace App\Lib;

use App\Exceptions\MoodleConnectFailureException;
use App\Exceptions\MoodleDownForMaintenanceException;
use App\Models\ActionLog;
use App\Models\ErrorLog;
use App\Models\OnlineCourse;
use App\Models\Person;
use App\Models\PersonOnlineCourse;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use stdClass;

class Moodle
{
    public ?string $domain;
    public ?string $token;
    public ?string $serviceName;
    public ?int $studentRoleId;

    const LOGIN_URL = '/login/token.php';
    const WEB_SERVICE_URL = '/webservice/rest/server.php';

    const WS_COURSE_AVAILABLE = 'core_course_get_courses';
    const WS_COURSE_COMPLETION = 'core_completion_get_course_completion_status';
    const WS_SEARCH_USERS = 'core_user_get_users';
    const WS_CREATE_USERS = 'core_user_create_users';
    const WS_ENROLL_USERS = 'enrol_manual_enrol_users';
    const WS_ENROLLED_USERS = 'core_enrol_get_enrolled_users';
    const WS_UPDATE_USERS = 'core_user_update_users';

    public function __construct()
    {
        $settings = setting(['MoodleDomain', 'MoodleToken', 'MoodleServiceName', 'MoodleStudentRoleID'], true);

        $this->domain = $settings['MoodleDomain'];
        $this->token = $settings['MoodleToken'];
        $this->serviceName = $settings['MoodleServiceName'];
        $this->studentRoleId = $settings['MoodleStudentRoleID'];
    }

    /**
     * Find all available courses.
     *
     * @return array
     * @throws MoodleDownForMaintenanceException
     */

    public function retrieveAvailableCourses(): array
    {
        $courses = $this->requestWebService('GET', self::WS_COURSE_AVAILABLE);
        return array_values(array_filter($courses, fn($r) => $r->format != 'site'));

    }

    /**
     * Retrieve a single course
     *
     * @param $courseId
     * @return ?stdClass
     * @throws MoodleDownForMaintenanceException
     */

    public function retrieveCourseInfo($courseId): ?stdClass
    {
        $result = $this->requestWebService('GET', self::WS_COURSE_AVAILABLE, [
            'options' => [
                'ids' => [$courseId]
            ]
        ]);

        return $result[0] ?? null;
    }

    /**
     * Find user(s) by email address.
     *
     * @param $query
     * @return array
     * @throws MoodleDownForMaintenanceException
     */

    public function findPersonByEmail($query): array
    {
        $result = $this->requestWebService('GET', self::WS_SEARCH_USERS, [
            'criteria' => [['key' => 'email', 'value' => self::normalizeEmail($query)]]
        ]);
        return $result->users ?? [];
    }

    /**
     * Find a user by the moodle id
     *
     * @param $id
     * @return stdClass|null
     * @throws MoodleDownForMaintenanceException
     */

    public function findPersonByMoodleId($id): ?stdClass
    {
        $result = $this->requestWebService('GET', self::WS_SEARCH_USERS, [
            'criteria' => [['key' => 'id', 'value' => $id]]
        ]);
        return ($result->users ?? [])[0] ?? null;
    }

    /**
     * Find everyone who is enrolled in a given course.
     *
     * @param int $courseId
     * @return array
     * @throws MoodleDownForMaintenanceException
     */

    public function retrieveCourseEnrollment(int $courseId): array
    {
        return $this->requestWebService('GET', self::WS_ENROLLED_USERS, ['courseid' => $courseId]);
    }

    /**
     * Check to see if a person has completed a given course
     *
     * @param int $courseId
     * @param $userId
     * @return mixed
     * @throws MoodleDownForMaintenanceException
     */

    public function retrieveCourseCompletion(int $courseId, $userId): mixed
    {
        return $this->requestWebService('GET', self::WS_COURSE_COMPLETION, ['courseid' => $courseId, 'userid' => $userId]);

    }

    /**
     * Retrieve everyone who is enrolled in a given course and check to see if
     * they have completed the course. NOTE: This is a *** SLOW *** lookup due to the number of API requests fired off.
     *
     * @param int $courseId
     * @return array
     * @throws MoodleDownForMaintenanceException
     */

    public function retrieveCourseEnrollmentWithCompletion(int $courseId): array
    {
        $students = $this->retrieveCourseEnrollment($courseId);
        $people = $this->findClubhouseUsers($students);
        $idNumbers = [];

        foreach ($students as $student) {
            if (!empty($student->idnumber)) {
                $idNumbers[$student->idnumber] = $student;
            }

            if (!$this->isStudentRole($student)) {
                $student->is_teacher = true;
                continue;
            }

            $completion = $this->retrieveCourseCompletion($courseId, $student->id);
            if (!$completion->completionstatus->completed) {
                continue;
            }

            $finished = self::latestCompletionTimestamp($completion->completionstatus);

            if ($finished) {
                $student->completed_at = (string)Carbon::createFromTimestamp($finished)->tz('America/Phoenix');
            }
        }

        foreach ($people as $row) {
            $student = $idNumbers[$row->id] ?? null;
            if (!$student) {
                continue;
            }

            $person = $row->person;
            $student->person = (object)[
                'id' => $person->id,
                'callsign' => $person->callsign,
                'status' => $person->status
            ];
        }

        usort($students, function ($a, $b) {
            if (isset($a->person) && isset($b->person)) {
                return strcasecmp($a->person->callsign, $b->person->callsign);
            }
            if (isset($a->person)) {
                return -1;
            }
            if (isset($b->person)) {
                return 1;
            }
            return strcasecmp($a->email, $b->email);
        });
        return $students;
    }

    /**
     * Update a user's profile.
     *
     * @param array $user
     * @return mixed
     * @throws MoodleDownForMaintenanceException
     */

    public function updateUser(array $user): mixed
    {
        $result = $this->requestWebService('POST', self::WS_UPDATE_USERS, ['users' => [$user]]);
        self::checkForWarnings($result, self::WS_UPDATE_USERS);

        return $result;
    }

    /**
     * Run thru an enrollment roster, try to associate Moodle users with Clubhouse accounts,
     * and mark those found as completing the course.
     *
     * @param OnlineCourse $course
     * @throws MoodleDownForMaintenanceException
     */

    public function processCourseCompletion(OnlineCourse $course): void
    {
        $courseId = $course->course_id;
        $year = $course->year;
        $enrolled = $this->retrieveCourseEnrollment($courseId);
        $students = $this->findClubhouseUsers($enrolled);

        $peopleIds = [];
        foreach ($students as $student) {
            $peopleIds[] = $student->person->id;
        }

        $completedAlready = [];
        if (!empty($peopleIds)) {
            $peopleCompleted = PersonOnlineCourse::whereIntegerInRaw('person_id', $peopleIds)
                ->where('position_id', $course->position_id)
                ->where('year', $year)
                ->whereNotNull('completed_at')
                ->get();
            foreach ($peopleCompleted as $person) {
                $completedAlready[$person->person_id] = true;
            }
        }

        foreach ($students as $student) {
            $person = $student->person;

            if (isset($completedAlready[$person->id])) {
                continue;
            }

            if (!$this->isStudentRole($student)) {
                // Not a student, teachers are not marked as completed.
                continue;
            }

            $result = $this->retrieveCourseCompletion($courseId, $student->id);
            if (!$result->completionstatus->completed) {
                continue;
            }

            $finished = self::latestCompletionTimestamp($result->completionstatus);

            if ($finished) {
                $completed = Carbon::createFromTimestamp($finished)->tz('America/Phoenix');
                if ($completed->year != $year) {
                    // Not completed in the course year.
                    continue;
                }
            } else {
                $completed = now();
            }

            $poc = PersonOnlineCourse::firstOrNewForPersonYear($person->id, $year, $course->position_id);
            $poc->online_course_id = $course->id;
            $poc->completed_at = $completed;
            $poc->auditReason = 'course completion';
            $poc->saveWithoutValidation();
        }
    }

    /**
     * Check if a Moodle enrollment roster entry holds the configured student role.
     *
     * @param stdClass $student
     * @return bool
     */

    private function isStudentRole(stdClass $student): bool
    {
        foreach ($student->roles as $role) {
            if ($role->roleid == $this->studentRoleId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find the latest (max) timecompleted timestamp among a course completion status's completions.
     *
     * @param stdClass $completionStatus
     * @return int
     */

    private static function latestCompletionTimestamp(stdClass $completionStatus): int
    {
        $finished = 0;
        foreach ($completionStatus->completions as $c) {
            if ($c->timecompleted > $finished) {
                $finished = $c->timecompleted;
            }
        }

        return $finished;
    }

    /**
     * Bulk look up Clubhouse accounts by using the LMS ID.
     *
     * @param $users
     * @return array
     */

    public function findClubhouseUsers($users): array
    {
        $ids = [];
        foreach ($users as $user) {
            if (!empty($user->idnumber)) {
                $ids[] = (int)$user->idnumber;
            }
        }

        if (empty($ids)) {
            return [];
        }

        $peopleById = Person::query()
            ->select('person.id', 'person.callsign', 'person.status', 'person.email', 'person.lms_id')
            ->whereIntegerInRaw('id', $ids)
            ->get()
            ->keyBy('id');


        $found = [];

        foreach ($users as $row) {
            if (empty($row->idnumber)) {
                continue;
            }
            $person = $peopleById[(int)$row->idnumber] ?? null;
            if ($person) {
                $row->person = $person;
                $found[] = $row;
            }
        }

        return $found;
    }

    /**
     * Try to link the Clubhouse account with Moodle.
     *
     * @param Person $person account to link
     * @return bool true if the Moodle user was found
     * @throws MoodleDownForMaintenanceException
     */

    public function findPerson(Person $person): bool
    {
        /*
         * Look up by email
         */
        $result = $this->findPersonByEmail($person->email);
        if (!empty($result)) {
            $user = $result[0];
            $person->lms_username = $user->username;
            $person->lms_id = $user->id;
            $person->auditReason = 'linked moodle account';
            $person->saveWithoutValidation();
            return true;
        }

        return false;
    }

    /**
     * Generate a password based off the user's real name.
     * <LastName><First Initial>!<6 random numbers>
     *
     * The generated password will be padded out to 10 characters with random letters.
     *
     * The random portions are generated with a cryptographically secure source
     * (random_int()) rather than rand()/str_shuffle(), since this password is used
     * for real Moodle account creation and password resets.
     *
     * @param Person $person
     * @return string
     */

    public static function generatePassword(Person $person): string
    {
        $letters = 'abcdefghijk';
        $lastName = ucfirst(strtolower(Person::convertDiacritics($person->last_name)));
        $firstName = Person::convertDiacritics($person->desired_first_name());
        $password = ucfirst(preg_replace('/[^\w]/', '', $lastName) . ucfirst(substr($firstName, 0, 1))) . '!';

        for ($i = 0; $i < 6; $i++) {
            $password .= random_int(0, 9);
        }

        while (strlen($password) < 10) {
            $password .= $letters[random_int(0, strlen($letters) - 1)];
        }

        // Ensure at least one lower case letter appears.
        if (!preg_match("/[a-z]/", $password)) {
            $password .= $letters[random_int(0, strlen($letters) - 1)];
        }

        return $password;
    }

    /**
     * Build up the username name.
     *
     * @param Person $person
     * @return string
     */

    public static function buildMoodleUsername(Person $person): string
    {
        $username = str_ireplace('(NR)', '', $person->callsign);
        $username = strtolower(trim($username));

        return preg_replace('/[^\w]/', '', Person::convertDiacritics($username));
    }

    /**
     * Create a Moodle user.
     *
     * @param Person $person
     * @param  $password
     * @return bool
     * @throws MoodleDownForMaintenanceException
     */

    public function createUser(Person $person, &$password): bool
    {
        $password = self::generatePassword($person);
        $username = self::buildMoodleUsername($person);

        $result = $this->requestWebService(
            'POST', self::WS_CREATE_USERS,
            [
                'users' => [[
                    'username' => $username,
                    'email' => self::normalizeEmail($person->email),
                    'password' => $password,
                    'firstname' => $person->desired_first_name(),
                    'lastname' => $person->last_name,
                    'idnumber' => $person->id
                ]]
            ]
        );
        if (empty($result[0]->id)) {
            ErrorLog::record('lms-request-failure', ['service' => self::WS_CREATE_USERS, 'result' => $result]);
            throw new RuntimeException('LMS create user response missing id');
        }

        $person->lms_username = $username;
        $person->lms_id = $result[0]->id;
        $person->auditReason = 'moodle account creation';
        $person->saveWithoutValidation();
        ActionLog::record(Auth::user(), 'lms-user-create', '', [
            'lms_id' => $person->lms_id,
            'lms_username' => $username,
        ], $person->id);

        return true;
    }

    /**
     * Enroll a person in a Moodle course
     *
     * @param Person $person person to enroll
     * @param PersonOnlineCourse $poc
     * @param OnlineCourse $course
     * @throws MoodleDownForMaintenanceException
     */

    public function enrollPerson(Person $person, PersonOnlineCourse $poc, OnlineCourse $course): void
    {
        if ($poc->online_course_id == $course->id) {
            // Person already enrolled
            return;
        }

        $result = $this->requestWebService('POST', self::WS_ENROLL_USERS, [
            'enrolments' => [
                [
                    'userid' => $person->lms_id,
                    'courseid' => $course->course_id,
                    'roleid' => (int)setting('MoodleStudentRoleID', true),
                ]
            ]
        ]);
        self::checkForWarnings($result, self::WS_ENROLL_USERS);

        $poc->online_course_id = $course->id;
        $poc->enrolled_at = now();
        $poc->saveWithoutValidation();

        ActionLog::record(Auth::user(), 'lms-enrollment', '', ['lms_course_id' => $course->course_id, 'online_course_id' => $course->id], $person->id);
    }

    /**
     * Scan an enrollment to see who is missing a Clubhouse ID
     *
     * @param $courseId
     * @return array
     * @throws MoodleDownForMaintenanceException
     */

    public function linkUsersInCourse($courseId): array
    {
        $students = $this->retrieveCourseEnrollment($courseId);

        $ids = [];
        $emails = [];
        foreach ($students as $student) {
            if (!empty($student->idnumber)) {
                $ids[] = (int)$student->idnumber;
            }
            $emails[] = $student->email;
        }

        $peopleById = Person::query()->whereIntegerInRaw('id', $ids)->get()->keyBy('id');
        $peopleByEmail = Person::query()->whereIn('email', $emails)->get()->keyBy('email');

        $notFound = [];
        $updated = [];

        foreach ($students as $student) {
            if (!empty($student->idnumber)) {
                $person = $peopleById[(int)$student->idnumber] ?? null;
                if ($person && $person->lms_id == $student->id && $person->lms_username == $student->username) {
                    // Looks good!
                    continue;
                }
            }

            $person = $peopleByEmail[$student->email] ?? null;
            if (!$person) {
                $notFound[] = $student;
                continue;
            }
            $person->lms_id = $student->id;
            $person->lms_username = $student->username;
            $person->auditReason = 'moodle user id association';
            $person->saveWithoutValidation();

            $clubhouseId = $person->id;
            $this->updateUser([
                'id' => $student->id,
                'idnumber' => $clubhouseId
            ]);
            $student->idnumber = $clubhouseId;
            $updated[] = $student;
        }

        return [$updated, $notFound];
    }

    /**
     * Sync the user's moodle information (including username) with the Clubhouse info.
     *
     * @param Person $person
     * @return void
     * @throws MoodleDownForMaintenanceException
     */

    public function syncPersonInfo(Person $person): void
    {
        $username = self::buildMoodleUsername($person);
        $person->lms_username = $username;

        $this->updateUser([
            'id' => $person->lms_id,
            'username' => $username,
            'email' => self::normalizeEmail($person->email),
            'firstname' => $person->desired_first_name(),
            'lastname' => $person->last_name,
        ]);
    }

    /**
     * Reset the person's password
     *
     * @param Person $person
     * @param $password
     * @return void
     * @throws MoodleDownForMaintenanceException
     */

    public function resetPassword(Person $person, &$password): void
    {
        $password = self::generatePassword($person);

        $this->updateUser([
            'id' => $person->lms_id,
            'password' => $password,
        ]);
    }

    /**
     * Make a Moodle API request
     *
     * @param string $method HTTP verb (GET, POST, PUT, etc.)
     * @param string $service
     * @param array $data
     * @return mixed
     * @throws MoodleDownForMaintenanceException
     */

    public function requestWebService(string $method, string $service, array $data = []): mixed
    {
        $query = [
            'wstoken' => $this->token,
            'moodlewsrestformat' => 'json',
            'wsfunction' => $service,
            ...$data
        ];

        $url = $this->domain . self::WEB_SERVICE_URL . '?' . http_build_query($query);
        $requestPath = $this->domain . self::WEB_SERVICE_URL;
        $client = Http::connectTimeout(10)->timeout(30);
        try {
            $response = match ($method) {
                'GET' => $client->get($url),
                'POST' => $client->asForm()->post($url),
                default => throw new RuntimeException("Unknown method [$method]"),
            };
        } catch (ConnectionException $exception) {
            $message = $exception->getMessage();
            ErrorLog::record('moodle-connect-failure', [
                'message' => $message,
                'url' => $requestPath,
                'query' => self::redactSensitiveData($query)
            ]);
            throw new MoodleConnectFailureException($message);
        }

        return self::decodeResponse($response, $requestPath);
    }

    /**
     * Decode the response from Moodle server and return a json object.
     *
     * @param $response
     * @param string $requestPath the request path (domain + endpoint) for logging, without the querystring
     * @return mixed
     * @throws MoodleDownForMaintenanceException
     */

    public static function decodeResponse($response, string $requestPath): mixed
    {
        if ($response->failed()) {
            $body = $response->body();
            if (str_contains(strtolower($body), 'undergoing maintenance')) {
                ErrorLog::record('lms-down-for-maintenance');
                throw new MoodleDownForMaintenanceException();
            }

            $status = $response->status();
            ErrorLog::record('lms-request-failure', [
                'status' => $status,
                'body' => $body,
                'url' => $requestPath,
            ]);
            throw new RuntimeException('HTTP LMS request status error status=' . $status);
        }

        try {
            // Try to decode the token
            $json = json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            ErrorLog::recordException($e, 'lms-decode-exception', [
                'body' => $response->body(),
                'url' => $requestPath,
            ]);
            throw new RuntimeException('LMS JSON decode exception');
        }

        if (isset($json->exception)) {
            ErrorLog::record('lms-request-failure', ['json' => $json, 'url' => $requestPath]);
            throw new RuntimeException('LMS request exception ' . $json->exception);
        }

        return $json;
    }

    /**
     * Check a decoded Moodle write response for a non-empty top-level "warnings"
     * array. Moodle write services can return HTTP 200 with no top-level
     * "exception" but a populated "warnings" array, meaning the operation was
     * actually skipped/rejected server-side.
     *
     * @param mixed $result the decoded Moodle response
     * @param string $service the wsfunction name, for logging
     * @return void
     */

    private static function checkForWarnings(mixed $result, string $service): void
    {
        $warnings = is_object($result) ? ($result->warnings ?? []) : [];
        if (empty($warnings)) {
            return;
        }

        ErrorLog::record('lms-request-warning', ['service' => $service, 'warnings' => $warnings]);
        throw new RuntimeException('LMS request warning for ' . $service);
    }

    /**
     * Redact sensitive keys (wstoken, password) from a request payload before
     * it is written to the error log.
     *
     * @param array $data
     * @return array
     */

    public static function redactSensitiveData(array $data): array
    {
        foreach (['wstoken', 'password'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = '[REDACTED]';
            }
        }

        return $data;
    }

    /**
     * Strip any alias out of an email address.
     * e.g., convert 'account+alias@domain.com' to 'account@domain.com'
     *
     * Moodle cannot deal with such email addresses
     *
     * @param string $email
     * @return string
     */

    public static function normalizeEmail(string $email): string
    {
        return preg_replace('/(\+.*)(?=\@)/', '', $email);
    }
}

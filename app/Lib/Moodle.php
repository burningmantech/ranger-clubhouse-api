<?php

namespace App\Lib;

use App\Exceptions\MoodleDownForMaintenanceException;
use App\Models\ActionLog;
use App\Models\ErrorLog;
use App\Models\OnlineCourse;
use App\Models\Person;
use App\Models\PersonOnlineCourse;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use stdClass;

class Moodle
{
    public $domain;
    public $token;
    public $serviceName;
    public $clientId;
    public $clientSecret;
    public $studentRoleId;

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
     * Attempt to get a login token to make Moodle web service requests.
     *
     * @return void
     * @throws MoodleDownForMaintenanceException
     */

    public function retrieveAccessToken(): void
    {
        $query = [
            'username' => $this->clientId,
            'password' => $this->clientSecret,
            'service' => $this->serviceName,
        ];

        $url = $this->domain . '/' . self::LOGIN_URL . '?' . http_build_query($query);
        $request = Http::connectTimeout(10);
        $response = $request->post($url);
        $json = self::decodeResponse($response, $url);
        $this->token = $json->token;
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
     * @return ?array
     * @throws MoodleDownForMaintenanceException
     */

    public function retrieveCourseInfo($courseId): mixed
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
        return $result->users;
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
        return $result->users[0] ?? null;
    }

    /**
     * Find everyone who is enrolled in a given course.
     *
     * @param int $courseId
     * @return mixed
     * @throws MoodleDownForMaintenanceException
     */

    public function retrieveCourseEnrollment(int $courseId): mixed
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

            $isStudent = false;
            foreach ($student->roles as $role) {
                if ($role->roleid == $this->studentRoleId) {
                    $isStudent = true;
                    break;
                }
            }

            if (!$isStudent) {
                $student->is_teacher = true;
                continue;
            }

            $completion = $this->retrieveCourseCompletion($courseId, $student->id);
            if (!$completion->completionstatus->completed) {
                continue;
            }

            $finished = 0;
            foreach ($completion->completionstatus->completions as $c) {
                if ($c->timecompleted > $finished) {
                    $finished = $c->timecompleted;
                }
            }

            if ($finished) {
                $student->completed_at = (string)Carbon::createFromTimestamp($finished);
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
                return 1;
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
        return $this->requestWebService('POST', self::WS_UPDATE_USERS, ['users' => [$user]]);
    }

    /**
     * Run thru an enrollment roster, try to associate Moodle users with Clubhouse accounts,
     * and mark those found as completing the course.
     *
     * @param OnlineCourse $course
     * @throws MoodleDownForMaintenanceException
     * @throws ValidationException
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

            $isStudent = false;
            foreach ($student->roles as $role) {
                if ($role->roleid == $this->studentRoleId) {
                    $isStudent = true;
                    break;
                }
            }

            if (!$isStudent) {
                // Not a student, teachers are not marked as completed.
                continue;
            }

            $result = $this->retrieveCourseCompletion($courseId, $student->id);
            if (!$result->completionstatus->completed) {
                continue;
            }

            $finished = 0;
            foreach ($result->completionstatus->completions as $c) {
                if ($c->timecompleted > $finished) {
                    $finished = $c->timecompleted;
                }
            }

            if ($finished) {
                $completed = Carbon::createFromTimestamp($finished);
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

            /*
             * Uncomment when a better solution to preventing spamming from multiple servers (aka the on playa server
             * fired up during the off season) is found.
            if (!in_array($person->status, Person::LOCKED_STATUSES)
                && !in_array($person->status, Person::NO_MESSAGES_STATUSES)) {
                mail_to_person($person, new OnlineCourseCompletedMail($person));
            }
            */
        }
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

        $peopleById = DB::table('person')
            ->select('person.id', 'person.callsign', 'person.status', 'person.email', 'person.lms_id')
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
     * <LastName><First Initial>!<3 random numbers>
     *
     * The generated password will be padded out to 8 characters with random letters.
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
        $password .= (rand(0, 9) . rand(0, 9) . rand(0, 9));

        while (strlen($password) < 10) {
            $password .= substr(str_shuffle($letters), 0, 1);
        }

        // Ensure at least one lower case letter appears.
        if (!preg_match("/[a-z]/", $password)) {
            $password .= substr(str_shuffle($letters), 0, 1);
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

        $this->requestWebService('POST', self::WS_ENROLL_USERS, [
            'enrolments' => [
                [
                    'userid' => $person->lms_id,
                    'courseid' => $course->course_id,
                    'roleid' => (int)setting('MoodleStudentRoleID', true),
                ]
            ]
        ]);

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

        $notFound = [];
        $updated = [];

        foreach ($students as $student) {
            if (!empty($student->idnumber)) {
                $person = Person::find($student->idnumber);
                if ($person && $person->lms_id == $student->id && $person->lms_username == $student->username) {
                    // Looks good!
                    continue;
                }
            }

            $person = Person::findByEmail($student->email);
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
        $client = Http::connectTimeout(10);
        $response = match ($method) {
            'GET' => $client->get($url),
            'POST' => $client->asForm()->post($url),
            default => throw new RuntimeException("Unknown method [$method]"),
        };

        return self::decodeResponse($response, $url);
    }

    /**
     * Decode the response from Moodle server and return a json object.
     *
     * @param $response
     * @param $url
     * @return mixed
     * @throws MoodleDownForMaintenanceException
     */

    public static function decodeResponse($response, $url): mixed
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
                'url' => $url,
            ]);
            throw new RuntimeException('HTTP LMS request status error status=' . $status);
        }

        try {
            // Try to decode the token
            $json = json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            ErrorLog::recordException($e, 'lms-decode-exception', [
                'body' => $response->body(),
                'url' => $url,
            ]);
            throw new RuntimeException('LMS JSON decode exception');
        }

        if (isset($json->exception)) {
            ErrorLog::record('lms-request-failure', ['json' => $json, 'url' => $url]);
            throw new RuntimeException('LMS request exception ' . $json->exception);
        }

        return $json;
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

<?php

namespace App\Lib;

use App\Mail\OnlineTrainingCompletedMail;
use App\Models\ActionLog;
use App\Models\ErrorLog;
use App\Models\Person;
use App\Models\PersonOnlineTraining;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Normalizer;
use RuntimeException;

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
     */

    public function retrieveAccessToken()
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
     */
    public function retrieveAvailableCourses(): array
    {
        return $this->requestWebService('GET', self::WS_COURSE_AVAILABLE);
    }

    /**
     * Find user(s) by email address.
     *
     * @param $query
     * @return array
     */

    public function findPersonByEmail($query): mixed
    {
        $result = $this->requestWebService('GET', self::WS_SEARCH_USERS, [
            'criteria' => [['key' => 'email', 'value' => $query]]
        ]);
        return $result->users;
    }

    /**
     * Find everyone who is enrolled in a given course.
     *
     * @param int $courseId
     * @return mixed
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
     */

    public function retrieveCourseCompletion(int $courseId, $userId): mixed
    {
        return $this->requestWebService('GET', self::WS_COURSE_COMPLETION, ['courseid' => $courseId, 'userid' => $userId]);

    }

    /**
     * Retrieve everyone who is enrolled in a given course and check to see if
     * they have completed the course. NOTE: This is a *** SLOW *** lookup due to
     *
     * @param int $courseId
     * @return array
     */
    public function retrieveCourseEnrollmentWithCompletion(int $courseId): array
    {
        $students = $this->retrieveCourseEnrollment($courseId);
        $people = $this->findClubhouseUsers($students);
        $idNumbers = [];
        foreach ($students as $student) {
            if (!empty($student->idnumber)) {
                $idNumbers[(int)$student->idnumber] = $student;
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

        foreach ($people as $person) {
            $student = $idNumbers[$person->id] ?? null;
            if (!$student) {
                continue;
            }

            $student->person = (object)['id' => $person->id, 'callsign' => $person->callsign, 'status' => $person->status];
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
     */

    public function updateUser(array $user): mixed
    {
        return $this->requestWebService('POST', self::WS_UPDATE_USERS, ['users' => [$user]]);
    }

    /**
     * Run thru an enrollment roster, try to associate Moodle users with Clubhouse accounts,
     * and mark those found as completing the course.
     *
     * @param int $courseId the Moodle course id to scan
     * @return void
     */

    public function processCourseCompletion(int $courseId): void
    {
        $enrolled = $this->retrieveCourseEnrollment($courseId);
        $students = $this->findClubhouseUsers($enrolled);

        $peopleIds = [];
        foreach ($students as $student) {
            $peopleIds[] = $student->person->id;
        }

        if (!empty($peopleIds)) {
            $peopleCompleted = PersonOnlineTraining::whereIntegerInRaw('person_id', $peopleIds)
                ->whereYear('completed_at', current_year())
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

            $ot = new PersonOnlineTraining([
                'person_id' => $person->id,
                'type' => PersonOnlineTraining::MOODLE,
                'completed_at' => $finished ? Carbon::createFromTimestamp($finished) : now()
            ]);
            $ot->auditReason = 'course completion';
            $ot->save();

            if (!in_array($person->status, Person::LOCKED_STATUSES)) {
                mail_to_person($person, new OnlineTrainingCompletedMail($person));
            }
        }
    }

    /**
     * Bulk look up Clubhouse accounts by using the LMS ID.
     *
     * @param $users
     * @return array
     */

    public function findClubhouseUsers($users)
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

        $people = Person::select('id', 'callsign', 'status', 'email', 'lms_id')
            ->whereIntegerInRaw('id', $ids)
            ->get();

        $found = [];

        $peopleById = [];
        foreach ($people as $person) {
            $peopleById[(int)$person->id] = $person;
        }

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
     */

    public function findPerson(Person $person): bool
    {
        if (!empty($person->lms_id)) {
            return true;
        }

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
        $firstName = Person::convertDiacritics($person->first_name);
        $password = ucfirst(preg_replace('/[^\w]/', '', $lastName) . ucfirst(substr($firstName, 0, 1))) . '!';
        $password .= ((string)rand(0, 9) . (string)rand(0, 9) . (string)rand(0, 9));

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
     * Create a Moodle user.
     *
     * @param Person $person
     * @param  $password
     * @return bool
     */

    public function createUser(Person $person, &$password): bool
    {
        $password = self::generatePassword($person);

        $username = str_ireplace('(NR)', '', $person->callsign);
        $username = strtolower(trim($username));
        $username = preg_replace('/[^\w]/', '', Person::convertDiacritics($username));

        $result = $this->requestWebService(
            'POST', self::WS_CREATE_USERS,
            [
                'users' => [[
                    'username' => $username,
                    'email' => strtolower($person->email),
                    'password' => $password,
                    'firstname' => $person->first_name,
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
     * @param int $courseId course to enroll into
     */

    public function enrollPerson(Person $person, int $courseId): void
    {
        if ($person->lms_course == $courseId) {
            // Person already enrolled
            return;
        }

        $this->requestWebService('POST', self::WS_ENROLL_USERS, [
            'enrolments' => [
                [
                    'userid' => $person->lms_id,
                    'courseid' => $courseId,
                    'roleid' => (int)setting('MoodleStudentRoleID', true),
                ]
            ]
        ]);

        $person->lms_course = $courseId;
        $person->saveWithoutValidation();

        ActionLog::record(Auth::user(), 'lms-enrollment', '', ['course_id' => $courseId], $person->id);
    }

    /**
     * Scan an enrollment to see who is missing a Clubhouse ID
     *
     * @param $course
     * @return array
     */

    public function linkUsersInCourse($courseId): array
    {
        $students = $this->retrieveCourseEnrollment($courseId);

        $notFound = [];
        $updated = [];

        foreach ($students as $student) {
            if (!empty($student->idnumber)) {
                $person = Person::find($student->idnumber);
                if ($person
                    && $person->lms_id == $student->id
                    && $person->lms_username == $student->username) {
                    // Looks good!
                    continue;
                }
            }

            $person = Person::findByEmail($student->email);
            if (!$person) {
                $notFound[] = $student;
                continue;
            }
            $person->lms_course = $courseId;
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
     * Make a Moodle API request
     *
     * @param string $method HTTP verb (GET, POST, PUT, etc.)
     * @param string $service
     * @param array $data
     * @return mixed
     */

    public function requestWebService(string $method, string $service, array $data = []): mixed
    {
        $query = [
            'wstoken' => $this->token,
            'moodlewsrestformat' => 'json',
            'wsfunction' => $service,
        ];

        $query = array_merge($query, $data);
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
     */

    public static function decodeResponse($response, $url): mixed
    {
        if ($response->failed()) {
            $status = $response->status();
            ErrorLog::record('lms-request-failure', [
                'status' => $status,
                'body' => $response->body(),
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
}

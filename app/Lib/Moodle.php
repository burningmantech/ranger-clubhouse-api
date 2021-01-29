<?php

namespace App\Lib;

use App\Models\ActionLog;
use App\Models\ErrorLog;
use App\Models\Person;
use App\Models\PersonOnlineTraining;

use App\Mail\OnlineTrainingCompletedMail;

use Carbon\Carbon;
use Exception;
use GuzzleHttp;
use GuzzleHttp\Exception\RequestException;

use Illuminate\Support\Facades\Auth;
use RuntimeException;

class Moodle
{
    public $domain;
    public $clientId;
    public $clientSecret;
    public $token;
    public $serviceName;

    const SERVICE_NAME = 'rangerweb';

    const LOGIN_URL = '/login/token.php';
    const WEB_SERVICE_URL = '/webservice/rest/server.php';

    const WS_COURSE_AVAILABLE = 'core_course_get_courses';
    const WS_COURSE_COMPLETION = 'core_completion_get_course_completion_status';
    const WS_SEARCH_USERS = 'tool_lp_search_users';
    const WS_CREATE_USERS = 'core_user_create_users';
    const WS_ENROLL_USERS = 'enrol_manual_enrol_users';
    const WS_ENROLLED_USERS = 'core_enrol_get_enrolled_users';

    public function __construct()
    {
        $settings = setting(['MoodleDomain', 'MoodleClientId', 'MoodleClientSecret', 'MoodleServiceName'], true);

        $this->domain = $settings['MoodleDomain'];
        $this->clientId = $settings['MoodleClientId'];
        $this->clientSecret = $settings['MoodleClientSecret'];
        $this->serviceName = $settings['MoodleServiceName'];

        $this->retrieveAccessToken();
    }

    public function retrieveAccessToken()
    {
        $json = $this->requestUrl('POST', self::LOGIN_URL, [
            'query' => [
                'username' => $this->clientId,
                'password' => $this->clientSecret,
                'service' => self::SERVICE_NAME
            ],
        ]);

        $this->token = $json->token;
    }

    public function retrieveAvailableCourses()
    {
        return $this->requestWebService('GET', self::WS_COURSE_AVAILABLE);

    }

    public function findPersonByQuery($query)
    {
        $result = $this->requestWebService('GET', self::WS_SEARCH_USERS, ['query' => $query, 'capability' => '']);
        return $result->users;
    }

    public function retrieveCourseEnrollment($courseId)
    {
        return $this->requestWebService('GET', self::WS_ENROLLED_USERS, ['courseid' => $courseId]);
    }

    public function retrieveCourseCompletion($courseId, $userId)
    {
        return $this->requestWebService('GET', self::WS_COURSE_COMPLETION, ['courseid' => $courseId, 'userid' => $userId]);

    }

    /**
     * Run thru an enrollment roster, try to associate Docebo users with Clubhouse accounts,
     * and mark those found as completing the course.
     *
     * @param int $courseId the Moodle course id to scan
     * @return void
     */

    public function processCourseCompletion(int $courseId): void
    {
        $enrolled = $this->retrieveCourseEnrollment($courseId);

        $people = $this->findClubhouseUsers($enrolled);

        $completedIds = [];
        foreach ($people as $row) {
            $person = $row->person;
            $result = $this->retrieveCourseCompletion($courseId, $row->id);

            if (!$result->completionstatus->completed) {
                continue;
            }

            $finished = 0;
            foreach ($result->completionstatus->completions as $c) {
                if ($c->timecompleted > $finished) {
                    $finished = $c->timecompleted;
                }
            }

            $completedIds[$person->id] = [$person, $finished];
        }

        if (empty($completedIds)) {
            return;
        }

        $peopleCompleted = PersonOnlineTraining::whereIn('person_id', array_keys($completedIds))
            ->whereYear('completed_at', current_year())
            ->get()
            ->keyBy('person_id');

        foreach ($completedIds as $personId => $row) {
            list ($person, $finished) = $row;

            if (isset($peopleCompleted[$person->id])) {
                continue;
            }

            PersonOnlineTraining::create([
                'person_id' => $person->id,
                'type' => PersonOnlineTraining::MOODLE,
                'completed_at' => $finished ? Carbon::createFromTimestamp($finished) : now()
            ]);

            if (!in_array($person->status, Person::LOCKED_STATUSES)) {
                mail_to($person->email, new OnlineTrainingCompletedMail($person));
            }
        }
    }

    public function findClubhouseUsers($users)
    {
        $personColumns = ['id', 'callsign', 'status', 'email', 'lms_id'];
        $peopleByLmsId = Person::select($personColumns)
            ->whereIn('lms_id', array_column($users, 'id'))
            ->get()
            ->keyBy('lms_id');

        $found = [];

        foreach ($users as $row) {
            $person = $peopleByLmsId[$row->id] ?? null;
            if ($person) {
                $row->person = $person;
                $found[] = $row;
            }
        }

        return $found;
    }

    /**
     * Try to link the Clubhouse account with Docebo.
     *
     * @param Person $person account to link
     * @return bool true if the Docebo user was found
     */

    public function findPerson(Person $person): bool
    {
        if (!empty($person->lms_id)) {
            return true;
        }

        /*
         * Try to associate by Clubhouse ID
         */
        $result = $this->findPersonByQuery('clubhouse-' . $person->id);
        if (!empty($result)) {
            self::setLmsID($person, $result->id);
            return true;
        }

        /*
         * Look up by email
         */
        $result = $this->findPersonByQuery($person->email);
        if (!empty($result)) {
            self::setLmsID($person, $result->id);
            return true;
        }

        return false;
    }

    public static function generatePassword(Person $person)
    {
        $password = ucfirst(preg_replace('/[^\w]/', '', $person->last_name) . ucfirst(substr($person->first_name, 0, 1))) .'!';
        $password .= ((string) rand(0,9) . (string) rand(0,9) . (string) rand(0, 9));

        while (strlen($password) < 8) {
            $password .= substr(str_shuffle("abcdef"), 0, 1);
        }
        return $password;
    }

    public function createUser(Person $person, &$password): bool
    {
        $password = self::generatePassword($person);

        $result = $this->requestWebService(
            'POST', self::WS_CREATE_USERS,
            [
                'users' => [[
                    'username' => $person->email,
                    'email' => $person->email,
                    'password' => $password,
                    'firstname' => $person->first_name,
                    'lastname' => $person->last_name,
                    'idnumber' => 'clubhouse-' . $person->id
                ]]
            ]
        );

        self::setLmsID($person, $result[0]->id);
        ActionLog::record(Auth::user(), 'lms-user-create', '', ['lms_id' => $person->lms_id], $person->id);
        return true;
    }

    /**
     * Enroll a person in a course
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
                    'courseid' => (int)$courseId,
                    'roleid' => (int)setting('MoodleStudentRoleID', true),
                ]
            ]
        ]);

        $person->lms_course = $courseId;
        $person->saveWithoutValidation();

        ActionLog::record(Auth::user(), 'lms-enrollment', '', ['course_id' => $courseId], $person->id);
    }


    /**
     * Execute a Moodle Web Service request
     *
     * @param string $method HTTP verb (GET, POST, PUT, etc.)
     * @param string $service
     * @param array $options
     * @return mixed
     */
    public function requestWebService(string $method, string $service, array $data = [])
    {
        $opts = [
            'query' => [
                'wstoken' => $this->token,
                'moodlewsrestformat' => 'json',
                'wsfunction' => $service,
            ]
        ];

        if ($method == 'POST') {
            $opts['form_params'] = $data;
        } else {
            $opts['query'] = array_merge($opts['query'], $data);
        }

        return $this->requestUrl($method, self::WEB_SERVICE_URL, $opts);
    }


    /**
     * Retrieve a URL from
     *
     * @param string $method HTTP verb (GET, POST, PUT, etc.)
     * @param string $path
     * @param array $options
     * @return mixed
     */
    public function requestUrl(string $method, string $path, array $options = [])
    {
        $client = new GuzzleHttp\Client;
        $data = [
            'read_timeout' => 10,
            'connect_timeout' => 10,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ];

        if (isset($options['form_params'])) {
            $data['form_params'] = $options['form_params'];
            $data['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        if (isset($options['json'])) {
            $data['json'] = $options['json'];
        }

        if (isset($options['query'])) {
            $data['query'] = $options['query'];
        }

        try {
            $res = $client->request($method, $this->domain . $path, $data);
        } catch (RequestException $e) {
            ErrorLog::recordException($e, 'docebo-request-exception', [
                'path' => $path,
                'data' => $data,
            ]);
            throw new RuntimeException('Moodle request failure');
        }

        $body = $res->getBody()->getContents();

        $status = $res->getStatusCode();
        if ($status != 200) {
            ErrorLog::record('lms-request-failure', [
                'status' => $res->getStatusCode(),
                'body' => $body,
                'path' => $path,
                'data' => $data,
            ]);
            throw new RuntimeException('LMS request status error status=' . $status);
        }

        try {
            // Try to decode the token
            $json = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            ErrorLog::recordException($e, 'lms-decode-exception', [
                'body' => $body,
                'path' => $path,
                'data' => $data,
            ]);
            throw new RuntimeException('LMS JSON decode exception');
        }

        if (isset($json->exception)) {
            ErrorLog::record('lms-request-failure', [
                'json' => $json,
                'path' => $path,
                'data' => $data,
            ]);
            throw new RuntimeException('LMS request exception ' . $json->exception);
        }

        return $json;
    }

    public static function setLmsId(Person $p, $lmsId)
    {
        if ($p->lms_id == $lmsId) {
            return;
        }
        $p->lms_id = $lmsId;
        $p->saveWithoutValidation();
    }
}

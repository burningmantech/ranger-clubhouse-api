<?php

namespace App\Lib;

use App\Models\ActionLog;
use App\Models\ErrorLog;
use App\Models\Person;
use App\Models\PersonOnlineTraining;

use App\Mail\OnlineTrainingCompletedMail;

use Exception;
use GuzzleHttp;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

use Illuminate\Support\Facades\Auth;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

class Docebo
{
    public $domain;
    public $clientId;
    public $clientSecret;
    public $accessToken;
    public $username;
    public $password;

    const OAUTH2_TOKEN = '/oauth2/token';
    const COURSE_AVAILABLE = '/course/v1/courses';

    const RANGER_FIELD = 4;
    const BPGUID_FIELD = 12;

    const STUDENT_LEVEL = 3;

    public function __construct()
    {
        $settings = setting(['DoceboDomain', 'DoceboClientId', 'DoceboClientSecret', 'DoceboUsername', 'DoceboPassword'], true);

        $this->domain = $settings['DoceboDomain'];
        $this->clientId = $settings['DoceboClientId'];
        $this->clientSecret = $settings['DoceboClientSecret'];
        $this->username = $settings['DoceboUsername'];
        $this->password = $settings['DoceboPassword'];

        $this->retrieveAccessToken();
    }

    public function retrieveAccessToken()
    {
        $json = $this->request('POST', self::OAUTH2_TOKEN, [
            'form_params' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'password',
                'username' => $this->username,
                'password' => $this->password,
                'scope' => 'api',
            ],
            'no_auth' => true
        ]);

        $this->accessToken = $json->access_token;
    }


    public function retrieveAvailableCourses()
    {
        $courses = $this->request('GET', self::COURSE_AVAILABLE, [
            'query' => ['page' => 1, 'page_size' => 200]
        ]);

        return $courses->data->items;
    }

    public function retrieveUserInfo($userId)
    {
        return $this->request('GET', "/manage/v1/user/{$userId}")->data;
    }


    public function retrieveCourseEnrollment($courseId)
    {
        $people = $this->request('GET', "/course/v1/courses/{$courseId}/enrollments",
            ['query' => ['page' => 1, 'page_size' => 5000]]
        )->data->items;

        // Try to link up everyone
        $this->linkUsers($people);
        return $people;
    }

    public function retrieveAllRangers()
    {
        $people = $this->request('GET', '/manage/v1/user',
            [
                'query' => [
                    'page' => 1,
                    'page_size' => 5000,
                    'filters' => json_encode([[
                        'field_' . self::RANGER_FIELD => ['option' => 'like', 'value' => 'Yes']
                    ]])
                ]
            ]);

        return $people->data->items;
    }

    public function retrieveUserByBPGUID($bpguid)
    {
        $people = $this->request('GET', '/manage/v1/user', [
            'query' => [
                'filters' => json_encode([[
                    'field_' . self::BPGUID_FIELD => ['option' => 'like', 'value' => $bpguid]
                ]])
            ]
        ]);

        return $people->data->items;
    }

    public function retrievePersonByEmail($email)
    {
        $people = $this->request('GET', '/manage/v1/user', ['query' => ['search_text' => $email]]);

        return $people->data->items;
    }

    /**
     * Run thru an enrollment roster, try to associate Docebo users with Clubhouse accounts,
     * and mark those found as completing the course.
     *
     * @param array $users - array of users as returned from Enrolled Course API.
     * @return void
     */

    public function linkUsersAndMarkComplete(array $users): void
    {
        $this->linkUsers($users);

        $completedIds = [];

        foreach ($users as $row) {
            $person = $row->person ?? null;
            $completed = $row->date_complete ?? null;
            if ($person && $completed) {
                $completedIds[$person->id] = [$person, $completed];
            }
        }

        if (!empty($completedIds)) {
            $peopleCompleted = PersonOnlineTraining::whereIn('person_id', array_keys($completedIds))
                ->whereYear('completed_at', current_year())
                ->get()
                ->keyBy('person_id');

            foreach ($completedIds as $personId => $row) {
                list ($person, $dateCompleted) = $row;

                if (isset($peopleCompleted[$person->id])) {
                    continue;
                }

                PersonOnlineTraining::create([
                    'person_id' => $person->id,
                    'type' => PersonOnlineTraining::DOCEBO,
                    'completed_at' => $dateCompleted
                ]);

                if (!in_array($person->status, Person::LOCKED_STATUSES)) {
                    mail_to($person->email, new OnlineTrainingCompletedMail($person));
                }
            }
        }

    }

    public function linkUsers(array $users): void
    {
        $personColumns = ['id', 'callsign', 'status', 'email', 'lms_id'];
        $peopleByUserId = Person::select($personColumns)
            ->whereIn('lms_id', array_column($users, 'user_id'))
            ->get()
            ->keyBy('lms_id');

        $emails = [];
        foreach ($users as $user) {
            if (!isset($peopleByUserId[$user->user_id])) {
                $emails[] = strtolower($user->email);
            }
        }

        $peopleByEmail = [];
        if (!empty($emails)) {
            $rows = Person::select($personColumns)
                ->whereIn('email', $emails)
                ->get();

            foreach ($rows as $row) {
                $peopleByEmail[strtolower($row->email)] = $row;
            }
        }

        foreach ($users as $row) {
            $person = $peopleByUserId[$row->user_id] ?? null;
            if (!$person) {
                $person = $peopleByEmail[strtolower($row->email)] ?? null;
            }

            if (!$person) {
                // Find the person by BPGUID
                $user = $this->retrieveUserInfo($row->user_id);
                if (!empty($user->additional_fields)) {
                    foreach ($user->additional_fields as $field) {
                        if ($field->id == self::BPGUID_FIELD && !empty($field->value)) {
                            $person = Person::select($personColumns)->where('bpguid', $field->value)->first();
                            break;
                        }
                    }
                }
            }

            if (!$person) {
                continue;
            }

            self::setLmsId($person, $row->user_id);
            $row->person = (object)[
                'id' => $person->id,
                'callsign' => $person->callsign,
                'status' => $person->status,
            ];
        }
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
         * Try to associate by BPGUID
         */
        if (!empty($person->bpguid)) {
            $people = $this->retrieveUserByBPGUID($person->bpguid);
            if (!empty($people)) {
                self::setLmsID($person, $people[0]->user_id);
                return true;
            }
        }

        /*
         * Look up by email
         */
        $people = $this->retrievePersonByEmail($person->email);
        if (!empty($people)) {
            self::setLmsID($person, $people[0]->user_id);
            return true;
        }

        return false;
    }

    public function createUser(Person $person, &$password)
    {
        $password = Person::generateRandomString();

        $fields = [
            self::RANGER_FIELD => 1,  // Yes, I iz Rangerz
        ];

        if (!empty($person->bpguid)) {
            $fields[self::BPGUID_FIELD] = $person->bpguid;
        }

        $result = $this->request(
            'POST', '/manage/v1/user',
            [
                'json' => [
                    'userid' => $person->email,
                    'email' => $person->email,
                    'firstname' => $person->first_name,
                    'lastname' => $person->last_name,
                    'password' => $password,
                    'level' => 6,
                    'email_validation_status' => 1,
                    'send_notification_email' => true,
                    'can_manage_subordinates' => false,
                    'additional_fields' => $fields,
                ]
            ]
        );

        self::setLmsID($person, $result->data->user_id);

        ActionLog::record(Auth::user(), 'docebo-user-create', '', [
            'lms_id' => $person->lms_id,
        ], $person->id);

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

        $this->request('POST', '/learn/v1/enrollments', [
            'json' => [
                'course_ids' => [$courseId],
                'user_ids' => [$person->lms_id],
                'level' => self::STUDENT_LEVEL,
            ]
        ]);

        $person->lms_course = $courseId;
        $person->saveWithoutValidation();

        ActionLog::record(Auth::user(), 'docebo-enrollment', '', [
            'course_id' => $courseId
        ], $person->id);
    }

    /**
     * Execute a Docebo API request
     *
     * @param string $method HTTP verb (GET, POST, PUT, etc.)
     * @param string $path
     * @param array $options
     * @return mixed
     */
    public function request(string $method, string $path, array $options = [])
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

        if (!isset($options['no_auth'])) {
            if (!$this->accessToken) {
                throw new RuntimeException("Access token has not been retrieved.");
            }
            $data['headers']['Authorization'] = "Bearer {$this->accessToken}";
        }

        try {
            $res = $client->request($method, 'https://' . $this->domain . $path, $data);
        } catch (RequestException $e) {
            ErrorLog::recordException($e, 'docebo-request-exception', [
                'path' => $path,
                'data' => $data,
            ]);
            throw new RuntimeException('Docebo connection failure');
        }

        $body = $res->getBody()->getContents();

        $status = $res->getStatusCode();
        if ($status != 200) {
            ErrorLog::record('docebo-request-failure', [
                'status' => $res->getStatusCode(),
                'body' => $body,
                'path' => $path,
                'data' => $data,
            ]);
            throw new RuntimeException('Docebo request status error status=' . $status);
        }

        try {
            // Try to decode the token
            $json = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            ErrorLog::recordException($e, 'docebo-decode-exception', [
                'body' => $body,
                'path' => $path,
                'data' => $data,
            ]);
            throw new RuntimeException('Docebo JSON decode exception');
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

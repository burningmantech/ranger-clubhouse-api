<?php

namespace App\Lib;

use App\Models\ErrorLog;
use App\Models\Person;
use App\Models\ProspectiveApplication;
use App\Models\ProspectiveApplicationLog;
use App\Models\ProspectiveApplicationNote;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProspectiveApplicationImport
{
    public SalesforceConnector $sf;

    public array $newApplications = [];
    public array $existingApplications = [];
    public array $creationFailures = [];
    public array $queryFailures = [];

    public ?string $errorMessage = null;

    const array STATUS_MAP = [
        'Not Yet Started' => ProspectiveApplication::STATUS_PENDING,
        'In VC Intake Process' => ProspectiveApplication::STATUS_PENDING,
        'Released to Upload' => ProspectiveApplication::STATUS_APPROVED,
        'FLAG - See VC Notes' => ProspectiveApplication::STATUS_HOLD_QUALIFICATION_ISSUE,
        'Clubhouse Import Failed' => ProspectiveApplication::STATUS_CREATED,
        'Clubhouse Record Not Updated' => ProspectiveApplication::STATUS_CREATED,
        'Clubhouse Record Updated' => ProspectiveApplication::STATUS_CREATED,
        'Clubhouse Record Created' => ProspectiveApplication::STATUS_CREATED,
        'STOP - Duplicate Record' => ProspectiveApplication::STATUS_DUPLICATE,
        'STOP - Past Prospective' => ProspectiveApplication::STATUS_CREATED,
        'STOP - See Notes' => ProspectiveApplication::STATUS_REJECT_UNQUALIFIED,
        'STOP - Not Qualified' => ProspectiveApplication::STATUS_REJECT_UNQUALIFIED,
        'STOP - UBERBONK' => ProspectiveApplication::STATUS_REJECT_UBERBONKED,
    ];

    const array EXPERIENCE_MAP = [
        'Never' => ProspectiveApplication::EXPERIENCE_NONE,
        'No' => ProspectiveApplication::EXPERIENCE_NONE,
        'Yes BRC1' => ProspectiveApplication::EXPERIENCE_BRC1,
        'Yes BRC1-RR1' => ProspectiveApplication::EXPERIENCE_BRC1R1,
        'Yes BRC2' => ProspectiveApplication::EXPERIENCE_BRC2,
        'Yes' => ProspectiveApplication::EXPERIENCE_BRC1,
    ];

    const array APPLICATION_RECORD_FIELDS = [
        'Id' => 'salesforce_id',
        'Name' => 'salesforce_name',
        'CH_UID__c' => 'person_id',
        'Applicant_BPGUID__c' => 'bpguid',
        'Applicant_First_Name__c' => 'first_name',
        'Applicant_Last_Name__c' => 'last_name',
        'Contact_Email__c' => 'email',
        'Known_Prospective_Volunteer_Names__c' => 'known_applicants',
        'Known_Rangers_Names__c' => 'known_rangers',
        'Ranger_Applicant_Type__c',
        'Submit_Radio_Call_Signs__c' => 'handles',
        'VC_Approved_Radio_Call_Sign__c' => 'approved_handle',
        'VC_Comments__c',
        'VC_Event_Year__c' => 'year',
        'VC_Status__c',
        'Why_Ranger__c' => 'why_volunteer',
        'Why_Ranger_Comments__c',
        'Regional_Ranger_Experience__c' => 'regional_experience',
        'Current_Regional_Callsign__c' => 'regional_callsign',
        'Ranger_Info_Over_18_Check__c',
        'Attended_Burning_Man_Twice__c',
    ];

    const array CONTACT_RECORD_FIELDS = [
        'Ranger_Info__r.Years_Attended_Burning_Man__c' => 'events_attended',
        'Ranger_Info__r.MailingStreet' => 'street',
        'Ranger_Info__r.MailingCity' => 'city',
        'Ranger_Info__r.MailingState' => 'state',
        'Ranger_Info__r.MailingCountry' => 'country',
        'Ranger_Info__r.MailingPostalCode' => 'postal_code',

        'Ranger_Info__r.MailingAddress',
        'Ranger_Info__r.Phone',
        'Ranger_Info__r.MobilePhone',
        'Ranger_Info__r.OtherPhone',
        'Ranger_Info__r.SFUID__c' => 'sfuid',
        'Ranger_Info__r.Emergency_Contact_Name__c',
        'Ranger_Info__r.Emergency_Contact_Phone__c',
        'Ranger_Info__r.Emergency_Contact_Relationship__c',
    ];

    const array PHONE_FIELDS = [
        'Phone',    // Home Phone
        'MobilePhone',
        'OtherPhone',
    ];


    public function __construct()
    {
        $this->sf = app(SalesforceConnector::class);
    }

    /**
     * Authenticate with salesforce
     *
     * @return bool
     */

    public function auth(): bool
    {
        return $this->sf->auth();
    }

    /**
     * Retrieve applications ready to import based on year, and query offset.
     *
     * @param int $year
     * @param int $offset
     * @return mixed
     */

    public function queryApplicationsForYear(int $year, int $offset): mixed
    {
        $sql = $this->buildSOQLBase()
            . " AND (Ranger_Applicant_Type__c = 'Prospective New Volunteer - Black Rock Ranger' OR Ranger_Applicant_Type__c = 'Prospective New Volunteer - Black Rock Ranger Redux')"
            . " AND CALENDAR_YEAR(CreatedDate)=$year";
        return $this->executeQuery($sql, $offset);
    }

    /**
     * Retrieve applications ready to import based on year, and query offset.
     *
     * @param int $offset
     * @return mixed
     */

    public function queryUnprocessedApplications(int $offset): mixed
    {
        $sql = $this->buildSOQLBase();

        // Use "View 1 - Check Qualifications" conditions to determine what applications to pull in.
        $sql .= " AND (VC_Status__c='Not Yet Started' OR VC_Status__c='In VC Intake Process')";

        $sql .= " AND (Ranger_Applicant_Type__c='Prospective New Volunteer - Black Rock Ranger'";
        $sql .= " OR Ranger_Applicant_Type__c='Prospective New Volunteer - Black Rock Ranger Redux'";
        $sql .= " OR Ranger_Applicant_Type__c='Training Auditor'";
        $sql .= " OR Ranger_Applicant_Type__c='Black Rock Ranger')";

        $sql .= " AND VC_Qualifications_Check__c='Needs checking'";

        // OR VC_Qualifications_Check__c='On hold / checking (see VC notes)')";

        //$sql .= " AND CALENDAR_YEAR(CreatedDate)=" . current_year();
        return $this->executeQuery($sql, $offset);
    }

    /**
     * Build up the base SOQL query from the desired columns, and exclude testing accounts.
     *
     * @return string
     */

    public function buildSOQLBase(): string
    {
        return 'SELECT ' . $this->buildFields(self::APPLICATION_RECORD_FIELDS) . " FROM Ranger__c WHERE (NOT VC_Approved_Radio_Call_Sign__c LIKE 'Testing%')";
    }

    public function buildFields(array $fields): string
    {
        $cols = [];
        foreach ($fields as $idx => $field) {
            $cols[] = is_numeric($idx) ? $field : $idx;
        }

        return implode(', ', $cols);
    }

    /**
     * Retrieve contact records for a batch of Ranger__c application Ids in a single query,
     * instead of one round-trip per application. Uses WITH USER_MODE (via executeQuery) so
     * that a record the running user cannot see is simply absent from the result -- and thus
     * from the returned map -- matching the per-Id inaccessibility semantics callers relied on.
     *
     * A failed query (Salesforce error) degrades to an empty map rather than throwing, so
     * every Id in the batch reads as inaccessible via `$map[$id] ?? false`.
     *
     * @param array<int, string> $ids Ranger__c record Ids to look up.
     * @return array<string, mixed> Map of Ranger__c Id => its record object. Callers should
     *                              use `$map[$id] ?? false` since a missing/inaccessible Id
     *                              is simply absent from the map.
     */

    public function queryContactRecords(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $quotedIds = implode(',', array_map(fn (string $id): string => "'" . addslashes($id) . "'", $ids));
        $sql = "SELECT Id, Ranger_Info__r.Id, " . $this->buildFields(self::CONTACT_RECORD_FIELDS) . " FROM Ranger__c WHERE Id IN ($quotedIds)";
        $results = $this->executeQuery($sql, 0, true);
        if ($results === false || empty($results->records)) {
            return [];
        }

        $contactsById = [];
        foreach ($results->records as $record) {
            $contactsById[$record->Id] = $record;
        }

        return $contactsById;
    }

    /**
     * Execute the SOQL statement, add query offset if given.
     *
     * @param string $sql
     * @param int $offset
     * @param bool $enforceSecurity
     * @return mixed
     */

    public function executeQuery(string $sql, int $offset, bool $enforceSecurity = false): mixed
    {
        if ($enforceSecurity) {
            $sql .= " WITH USER_MODE";
        }

        $sql .= " LIMIT 400";
        if ($offset) {
            $sql .= " OFFSET $offset";
        }

        $r = $this->sf->soqlQuery($sql);
        if (!$r) {
            if (!$enforceSecurity) {
                $this->errorMessage = "Salesforce API query failed for $sql: {$this->sf->errorMessage}";
            }
            return false;
        }

        return $r;
    }

    /**
     * Import records for a given year. Intended use is to seed the database with past applications.
     *
     * @param int $year
     * @param bool $commit
     * @return array
     */

    public function importForYear(int $year, bool $commit = false): array
    {
        $this->errorMessage = null;
        $this->paginate(fn (int $offset): mixed => $this->queryApplicationsForYear($year, $offset), false, $commit);

        return [$this->newApplications, $this->existingApplications];
    }

    /**
     * Import unprocessed applications.
     *
     * @param bool $commit
     * @return void
     */

    public function importUnprocessed(bool $commit): void
    {
        $this->errorMessage = null;
        $this->paginate(fn (int $offset): mixed => $this->queryUnprocessedApplications($offset), true, $commit);
    }

    /**
     * Page through a Salesforce list query, batching the contact lookups for each page
     * and importing every application row, until a page fails or comes back empty.
     *
     * @param callable $query Given an offset, returns the next page (mixed, per executeQuery()).
     * @param bool $setEventYear
     * @param bool $commit
     * @return void
     */

    private function paginate(callable $query, bool $setEventYear, bool $commit): void
    {
        $offset = 0;
        while (true) {
            $rows = $query($offset);
            if ($rows === false) {
                break;
            }

            if (empty($rows->records)) {
                break;
            }

            $contactsById = $this->queryContactRecords(array_column($rows->records, 'Id'));
            foreach ($rows->records as $row) {
                $this->importApplication($row, $contactsById[$row->Id] ?? false, $setEventYear, $commit);
            }

            $offset += count($rows->records);
        }
    }

    public function extractFields(array $fields, mixed $sfObj, ProspectiveApplication $application, bool $ignoreNameCheck = false): bool
    {
        $allEmpty = true;
        foreach ($fields as $field => $col) {
            if (!is_numeric($field)) {
                if (str_contains($field, '.')) {
                    list ($n, $c) = explode('.', $field);
                    $queryField = $c;
                } else {
                    $queryField = $field;
                }
                $value = $sfObj->{$queryField} ?? null;
                if (!empty($value)) {
                    $allEmpty = false;
                }
                $application->{$col} = $value ?? '';
            }
        }

        return $allEmpty;
    }

    /**
     * Import an application
     *
     * @param mixed $sobj
     * @param mixed $contactObj Pre-fetched contact record for this application's Id (or false
     *                          if missing/inaccessible), as returned by queryContactRecords().
     * @param bool $setEventYear
     * @param bool $commit
     */

    public function importApplication(mixed $sobj, mixed $contactObj, bool $setEventYear = false, bool $commit = false): void
    {
        $row = new ProspectiveApplication();
        $this->extractFields(self::APPLICATION_RECORD_FIELDS, $sobj, $row);
        if ($setEventYear) {
            $row->year = current_year();
        }

        $existing = ProspectiveApplication::findByYearSalesforceName($row->year, $row->salesforce_name);
        if ($existing) {
            $row->id = $existing->id;
            $this->existingApplications[] = $existing;
            return;
        }

        if (!$contactObj) {
            $row->api_error = ProspectiveApplication::API_ERROR_CONTACT_INACCESSIBLE;
            $this->queryFailures[] = $row;
            return;
        }

        $rInfo = $contactObj->Ranger_Info__r;
        if (!$rInfo) {
            $row->api_error = ProspectiveApplication::API_ERROR_CONTACT_INACCESSIBLE;
            $this->queryFailures[] = $row;
            return;
        }
        $row->contact_id = $rInfo->Id ?? null;
        $isBlank = $this->extractFields(self::CONTACT_RECORD_FIELDS, $rInfo, $row);
        if ($isBlank) {
            $row->api_error = ProspectiveApplication::API_ERROR_CONTACT_BLANK;
            $this->queryFailures[] = $row;
            return;
        }

        if (empty($row->person_id)) {
            // Treat blank as null.
            $row->person_id = null;
        }

        if (empty($row->bpguid)) {
            if ($row->person_id) {
                $bpguid = Person::whereKey($row->person_id)->value('bpguid');
                if (empty($bpguid)) {
                    $row->api_error = ProspectiveApplication::API_ERROR_MISSING_BPGUID;
                    $this->queryFailures[] = $row;
                    return;
                }
                $row->bpguid = $bpguid;
            } else {
                $row->api_error = ProspectiveApplication::API_ERROR_MISSING_BPGUID;
                $this->queryFailures[] = $row;
                return;
            }
        }

        $row->street = self::sanitizeStreet($rInfo);

        // Build up the emergency contact info
        $ecName = self::sanitizeField($rInfo, 'Emergency_Contact_Name__c');
        $ecRelation = self::sanitizeField($rInfo, 'Emergency_Contact_Relationship__c');
        $ecPhone = self::sanitizeField($rInfo, 'Emergency_Contact_Phone__c');

        $ec = [];
        if (!empty($ecName)) {
            $ec[] = $ecName;
        }

        if (!empty($ecRelation)) {
            $ec[] = "($ecRelation)";
        }

        if (!empty($ecPhone)) {
            $ec[] = "phone $ecPhone";
        }

        $row->emergency_contact = implode(" ", $ec);


        // Figure out what status to set.
        $status = self::sanitizeField($sobj, 'VC_Status__c');

        if ($status == 'STOP - Past Prospective') {
            $row->status = $row->person_id ? ProspectiveApplication::STATUS_CREATED : ProspectiveApplication::STATUS_PENDING;
        } else if (isset(self::STATUS_MAP[$status])) {
            $row->status = self::STATUS_MAP[$status];
        } else {
            $row->status = ProspectiveApplication::STATUS_PENDING;
        }

        $experience = self::sanitizeField($sobj, 'Attended_Burning_Man_Twice__c');
        if (empty($experience)) {
            $row->experience = ProspectiveApplication::EXPERIENCE_NONE;
        } else if (isset(self::EXPERIENCE_MAP[$experience])) {
            $row->experience = self::EXPERIENCE_MAP[$experience];
        } else {
            $row->experience = ProspectiveApplication::EXPERIENCE_NONE;
        }

        $row->is_over_18 = (self::sanitizeField($sobj, 'Ranger_Info_Over_18_Check__c') == "Yes");

        if ($row->status === ProspectiveApplication::STATUS_CREATED) {
            $row->why_volunteer_review = ProspectiveApplication::WHY_VOLUNTEER_REVIEW_OKAY;
        }

        if (!empty($row->approved_handle) && preg_match('/^(.*?)\s*\(\d+\)\s*$/', $row->approved_handle)) {
            // Recycled applications with previously assigned callsign have the format "Handle (YYYY)". Blank it out.
            $row->approved_handle = '';
        }

        foreach (self::PHONE_FIELDS as $field) {
            if (isset($rInfo->{$field})) {
                $phone = trim($rInfo->{$field});
                if (!empty($phone)) {
                    $row->phone = $phone;
                    break;
                }
            }
        }


        if (!$commit) {
            $this->newApplications[] = $row;
            return;
        }

        try {
            $validationFailed = DB::transaction(function () use ($row, $sobj, $status) {
                if (!$row->save()) {
                    $row->append('api_error_message');
                    $message = "Fields failed to validate:\n";
                    $errors = $row->getErrors();
                    foreach ($errors as $column => $messages) {
                        $message .= "$column: " . implode("\n", $messages) . "\n";
                    }
                    $row->api_error = ProspectiveApplication::API_ERROR_INVALID;
                    $row->api_error_message = $message;
                    $this->creationFailures[] = $row;
                    ErrorLog::record('prospective-application-validation-failure', ['record' => $row, 'errors' => $errors]);
                    return true;
                }

                ProspectiveApplicationLog::record($row->id,
                    ProspectiveApplicationLog::ACTION_IMPORTED,
                    [
                        'salesforce_status' => $status,
                        'salesforce_type' => self::sanitizeField($sobj, 'Ranger_Applicant_Type__c'),
                    ]);

                $comments = self::sanitizeField($sobj, 'VC_Comments__c');
                if (!empty($comments)) {
                    $this->insertNote($row->id, ProspectiveApplicationNote::TYPE_VC, $comments);
                }

                $why = self::sanitizeField($sobj, 'Why_Ranger_Comments__c');
                if (!empty($why)) {
                    $this->insertNote($row->id, ProspectiveApplicationNote::TYPE_VC_COMMENT, $why);
                }

                $this->newApplications[] = $row;

                return false;
            });
        } catch (Throwable $e) {
            $row->api_error = ProspectiveApplication::API_ERROR_CREATE_FAILURE;
            $row->append('api_error_message');
            $row->api_error_message = $e->getMessage();
            $this->creationFailures[] = $row;

            // error_logs is broadly readable, so persist only non-PII identifiers and a
            // sanitized exception summary here; the full message stays in api_error_message
            // above, for the VC/ADMIN caller who already owns this record's PII.
            ErrorLog::recordException($e, 'prospective-application-create-failure', [
                'record' => [
                    'salesforce_id' => $row->salesforce_id,
                    'salesforce_name' => $row->salesforce_name,
                    'api_error' => $row->api_error,
                ],
                'exception' => [
                    'message' => $e::class,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ]);
            return;
        }

        if ($validationFailed) {
            return;
        }
    }

    /**
     * Create a note attached to a prospective application.
     *
     * @param int $applicationId
     * @param string $type
     * @param string $note
     * @return void
     */

    private function insertNote(int $applicationId, string $type, string $note): void
    {
        ProspectiveApplicationNote::create([
            'type' => $type,
            'prospective_application_id' => $applicationId,
            'note' => $note,
        ]);
    }

    public static function sanitizeStreet(object $s): string
    {
        $s = $s->MailingStreet ?? '';
        $s = str_replace("\r", "", $s);
        $s = str_replace("\n", " ", $s);
        return trim($s);
    }

    public static function sanitizeField(object $obj, string $name): string
    {
        return trim($obj->{$name} ?? '');
    }
}

<?php

namespace App\Lib;

use App\Models\ProspectiveApplication;
use App\Models\ProspectiveApplicationLog;
use App\Models\ProspectiveApplicationNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProspectiveApplicationImport
{
    public SalesforceConnector $sf;

    public array $newApplications = [];
    public array $existingApplications = [];

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

    const array SF_FIELDS = [
        'Id' => 'salesforce_id',
        'CH_UID__c' => 'person_id',
        'Contact_Email__c' => 'email',
        'Known_Prospective_Volunteer_Names__c' => 'known_applicants',
        'Known_Rangers_Names__c' => 'known_rangers',
        'Name' => 'salesforce_name',
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
        'Ranger_Info__r.Years_Attended_Burning_Man__c' => 'events_attended',
        'Ranger_Info_Over_18_Check__c',
        'Attended_Burning_Man_Twice__c',

        'Ranger_Info__r.FirstName' => 'first_name',
        'Ranger_Info__r.LastName' => 'last_name',
        'Ranger_Info__r.MailingStreet' => 'street',
        'Ranger_Info__r.MailingCity' => 'city',
        'Ranger_Info__r.MailingState' => 'state',
        'Ranger_Info__r.MailingCountry' => 'country',
        'Ranger_Info__r.MailingPostalCode' => 'postal_code',

        'Ranger_Info__r.npe01__HomeEmail__c',
        'Ranger_Info__r.Phone',
        'Ranger_Info__r.MobilePhone',
        'Ranger_Info__r.OtherPhone',
        'Ranger_Info__r.BPGUID__c' => 'bpguid',
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
        $this->sf = new SalesforceConnector();
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
            . " AND VC_Event_Year__c='$year'";
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

        $sql .= " AND (VC_Qualifications_Check__c='Needs checking' OR VC_Qualifications_Check__c='On hold / checking (see VC notes)')";

        return $this->executeQuery($sql, $offset);
    }

    /**
     * Build up the base SOQL query from the desired columns, and exclude testing accounts.
     *
     * @return string
     */

    public function buildSOQLBase(): string
    {
        $cols = [];
        foreach (self::SF_FIELDS as $idx => $field) {
            if (is_numeric($idx)) {
                $cols[] = $field;
            } else {
                $cols[] = $idx;
            }
        }

        return 'SELECT ' . implode(', ', $cols) . " FROM Ranger__c WHERE (NOT VC_Approved_Radio_Call_Sign__c LIKE 'Testing%')";
    }

    /**
     * Execute the SOQL statement, add query offset if given.
     *
     * @param string $sql
     * @param int $offset
     * @return mixed
     */

    public function executeQuery(string $sql, int $offset): mixed
    {
        $sql .= " LIMIT 400";
        if ($offset) {
            $sql .= " OFFSET $offset";
        }

        $r = $this->sf->soqlQuery($sql);
        if (!$r
            || (is_array($r) && $r[0]->message != "")) {
            $this->sf->errorMessage = "Salesforce API query failed for $sql: {$this->sf->errorMessage}";
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
     * @throws ValidationException
     */

    public function importForYear(int $year, bool $commit = false): array
    {
        $offset = 0;
        while (true) {
            $rows = $this->queryApplicationsForYear($year, $offset);
            if ($rows === false) {
                break;
            }

            if (empty($rows->records)) {
                break;
            }

            foreach ($rows->records as $id => $row) {
                $this->importApplication($row, false, $commit);
            }

            $offset += count($rows->records);
        }

        return [$this->newApplications, $this->existingApplications];
    }

    /**
     * Import unprocessed applications.
     *
     * @param bool $commit
     * @return array
     * @throws ValidationException
     */

    public function importUnprocessed(bool $commit): array
    {
        $offset = 0;
        while (true) {
            $rows = $this->queryUnprocessedApplications($offset);
            if ($rows === false) {
                break;
            }

            if (empty($rows->records)) {
                break;
            }

            foreach ($rows->records as $id => $row) {
                $this->importApplication($row, true, $commit);
            }

            $offset += count($rows->records);
        }

        return [$this->newApplications, $this->existingApplications];
    }

    /**
     * Import an application
     *
     * @param mixed $sobj
     * @param bool $setEventYear
     * @param bool $commit
     * @throws ValidationException
     */

    public function importApplication(mixed $sobj, bool $setEventYear = false, bool $commit = false): void
    {
        $row = new ProspectiveApplication();

        $rInfo = $sobj->Ranger_Info__r;
        foreach (self::SF_FIELDS as $field => $col) {
            if (!is_numeric($field)) {
                if (str_contains($field, '.')) {
                    list ($n, $c) = explode('.', $field);
                    $value = $rInfo->{$c} ?? '';
                } else {
                    $value = $sobj->{$field};
                }
                $row->{$col} = $value ?? '';
            }
        }

        if (empty($row->person_id)) {
            // Treat blank as null.
            $row->person_id = null;
        }

        if (empty($row->bpguid)) {
            // Don't bother with applications without a BPGUID -- too old.
            if ($row->person_id) {
                $bpguid = DB::table('person')->where('id', $row->person_id)->value('bpguid');
                if (empty($bpguid)) {
                    return;
                }
                $row->bpguid = $bpguid;
            } else {
                return;
            }
        }

        $row->street = self::sanitizeStreet($rInfo);

        if (empty($row->email)) {
            $row->email = self::sanitizeField($rInfo, 'npe01__HomeEmail__c');
        }

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
        } else {
            $row->experience = self::EXPERIENCE_MAP[$experience];
        }

        $row->is_over_18 = (self::sanitizeField($sobj, 'Ranger_Info_Over_18_Check__c') == "Yes");

        if ($row->status === ProspectiveApplication::STATUS_CREATED) {
            $row->why_volunteer_review = ProspectiveApplication::WHY_VOLUNTEER_REVIEW_OKAY;
        }

        if (!empty($row->approved_handle) && preg_match('/^(.*?)\s*\(\d+\)\s*$/', $row->approved_handle)) {
            // Recycled applications with previously assigned callsign have the format "Handle (YYYY)". Blank it out.
            $row->approved_handle = '';
        }

        if ($setEventYear) {
            $row->year = current_year();
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


        $existing = ProspectiveApplication::findByYearSalesforceName($row->year, $row->salesforce_name);
        if ($existing) {
            $row->id = $existing->id;
            $this->existingApplications[] = $existing;
        } else {
            if ($commit) {
                $row->save();

                ProspectiveApplicationLog::record($row->id,
                    ProspectiveApplicationLog::ACTION_IMPORTED,
                    [
                        'salesforce_status' => $status,
                        'salesforce_type' => self::sanitizeField($sobj, 'Ranger_Applicant_Type__c'),
                    ]);

                $comments = self::sanitizeField($sobj, 'VC_Comments__c');
                if (!empty($comments)) {
                    ProspectiveApplicationNote::insert([
                        'type' => ProspectiveApplicationNote::TYPE_VC,
                        'prospective_application_id' => $row->id,
                        'note' => $comments,
                        'created_at' => now(),
                    ]);
                }

                $why = self::sanitizeField($sobj, 'Why_Ranger_Comments__c');
                if (!empty($why)) {
                    ProspectiveApplicationNote::insert([
                        'type' => ProspectiveApplicationNote::TYPE_VC_COMMENT,
                        'prospective_application_id' => $row->id,
                        'note' => $why,
                        'created_at' => now(),
                    ]);
                }
            }
            $this->newApplications[] = $row;
        }
    }

    public static function sanitizeStreet($s): string
    {
        $s = $s->MailingStreet ?? '';
        $s = str_replace("\r", "", $s);
        $s = str_replace("\n", " ", $s);
        return trim($s);
    }

    public static function sanitizeField($obj, $name): string
    {
        return trim($obj->{$name} ?? '');
    }
}
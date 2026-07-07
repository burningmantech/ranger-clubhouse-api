<?php

namespace Tests\Feature;

use App\Lib\ProspectiveApplicationImport;
use App\Lib\SalesforceConnector;
use App\Models\ErrorLog;
use App\Models\Person;
use App\Models\ProspectiveApplication;
use App\Models\ProspectiveApplicationLog;
use App\Models\ProspectiveApplicationNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\FakeSalesforceConnector;
use Tests\TestCase;

class ProspectiveApplicationImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a Salesforce "Ranger__c" application row as returned by the list queries.
     *
     * @param array $overrides
     * @return object
     */

    private function applicationRecord(array $overrides = []): object
    {
        $defaults = [
            'Id' => 'a01',
            'Name' => 'R-100',
            'CH_UID__c' => '',
            'Applicant_BPGUID__c' => 'BP-100',
            'Applicant_First_Name__c' => 'New',
            'Applicant_Last_Name__c' => 'Comer',
            'Contact_Email__c' => 'newcomer@example.com',
            'Known_Prospective_Volunteer_Names__c' => '',
            'Known_Rangers_Names__c' => '',
            'Ranger_Applicant_Type__c' => 'Prospective New Volunteer - Black Rock Ranger',
            'Submit_Radio_Call_Signs__c' => 'Newbie',
            'VC_Approved_Radio_Call_Sign__c' => '',
            'VC_Comments__c' => '',
            'VC_Event_Year__c' => (int) date('Y'),
            'VC_Status__c' => 'Not Yet Started',
            'Why_Ranger__c' => 'To help.',
            'Why_Ranger_Comments__c' => '',
            'Regional_Ranger_Experience__c' => '',
            'Current_Regional_Callsign__c' => '',
            'Ranger_Info_Over_18_Check__c' => 'Yes',
            'Attended_Burning_Man_Twice__c' => '',
        ];

        return (object) array_merge($defaults, $overrides);
    }

    /**
     * Build a contact record as returned per-Id by queryContactRecords(), i.e. an
     * accessible record with a non-blank Ranger_Info__r.
     *
     * @param string $id
     * @param array $rangerInfoOverrides
     * @return object
     */

    private function contactRecord(string $id = 'a01', array $rangerInfoOverrides = []): object
    {
        $rangerInfo = array_merge([
            'Id' => 'contact-01',
            'Years_Attended_Burning_Man__c' => '2',
            'MailingStreet' => '123 Playa Rd',
            'MailingCity' => 'Gerlach',
            'MailingState' => 'NV',
            'MailingCountry' => 'US',
            'MailingPostalCode' => '89412',
            'Phone' => '775-555-1212',
            'SFUID__c' => 'SF-100',
            'Emergency_Contact_Name__c' => '',
            'Emergency_Contact_Phone__c' => '',
            'Emergency_Contact_Relationship__c' => '',
        ], $rangerInfoOverrides);

        return (object) ['Id' => $id, 'Ranger_Info__r' => (object) $rangerInfo];
    }

    /**
     * Wrap a list of records into the {totalSize, records} envelope shape soqlQuery()
     * returns, for queueing as a canned FakeSalesforceConnector response.
     *
     * @param array $records
     * @return object
     */

    private function queryEnvelope(array $records): object
    {
        return (object) ['totalSize' => count($records), 'records' => $records];
    }

    /**
     * Bind a fake connector that answers successive soqlQuery() calls with the given
     * results, in order (falling back to an empty/false result once exhausted).
     *
     * @param object ...$queryResults
     * @return FakeSalesforceConnector
     */

    private function bindFakeConnector(object|false ...$queryResults): FakeSalesforceConnector
    {
        $fake = new FakeSalesforceConnector();
        $fake->queryResults = $queryResults;
        $this->app->instance(SalesforceConnector::class, $fake);

        return $fake;
    }

    /**
     * When the contact record is inaccessible (Salesforce's WITH USER_MODE row-level
     * sharing filters it out, returning an empty records array), the record is
     * reported as a query failure instead of crashing on an empty-array index.
     *
     * @return void
     */

    public function test_inaccessible_contact_record_is_reported_as_query_failure(): void
    {
        $import = new ProspectiveApplicationImport();
        $import->importApplication($this->applicationRecord(), false, false, false);

        $this->assertCount(1, $import->queryFailures);
        $this->assertEquals(ProspectiveApplication::API_ERROR_CONTACT_INACCESSIBLE, $import->queryFailures[0]->api_error);
        $this->assertEmpty($import->newApplications);
        $this->assertEmpty($import->creationFailures);
    }

    /**
     * A record whose linked Person has no bpguid is reported as a query failure with
     * API_ERROR_MISSING_BPGUID, matching the sibling no-person_id branch, instead of
     * being silently dropped.
     *
     * @return void
     */

    public function test_person_without_bpguid_is_reported_as_query_failure(): void
    {
        $person = Person::factory()->create(['bpguid' => null]);

        $import = new ProspectiveApplicationImport();
        $import->importApplication($this->applicationRecord([
            'Applicant_BPGUID__c' => '',
            'CH_UID__c' => (string) $person->id,
        ]), $this->contactRecord(), false, true);

        $this->assertCount(1, $import->queryFailures);
        $this->assertEquals(ProspectiveApplication::API_ERROR_MISSING_BPGUID, $import->queryFailures[0]->api_error);
        $this->assertEmpty($import->newApplications);
        $this->assertEmpty($import->creationFailures);
    }

    /**
     * A throw during the post-save log write (ProspectiveApplicationLog::record(), which
     * saves an Eloquent model and so can be faulted via a model event) rolls the whole
     * commit -- including the ProspectiveApplication row itself -- back, reports the
     * record as a creation failure, and does not abort the import: the next record is
     * still processed normally.
     *
     * @return void
     */

    public function test_throw_during_post_save_log_write_rolls_back_and_import_continues(): void
    {
        $shouldThrow = true;
        ProspectiveApplicationLog::creating(function () use (&$shouldThrow) {
            if ($shouldThrow) {
                throw new RuntimeException('forced log failure');
            }
        });

        $import = new ProspectiveApplicationImport();

        $import->importApplication($this->applicationRecord(['Id' => 'a01', 'Name' => 'R-9001']), $this->contactRecord(), false, true);

        $this->assertDatabaseMissing('prospective_application', ['salesforce_name' => 'R-9001']);
        $this->assertCount(1, $import->creationFailures);
        $this->assertEmpty($import->newApplications);
        $this->assertEquals(ProspectiveApplication::API_ERROR_CREATE_FAILURE, $import->creationFailures[0]->api_error);
        $this->assertStringContainsString('forced log failure', $import->creationFailures[0]->api_error_message);

        // The next record is unaffected -- the import loop was not aborted.
        $shouldThrow = false;
        $import->importApplication($this->applicationRecord(['Id' => 'a02', 'Name' => 'R-9002']), $this->contactRecord(), false, true);

        $this->assertDatabaseHas('prospective_application', ['salesforce_name' => 'R-9002']);
        $this->assertCount(1, $import->newApplications);
        $this->assertCount(1, $import->creationFailures);
    }

    /**
     * On a successful commit, non-blank VC_Comments__c and Why_Ranger_Comments__c values
     * are each written as a ProspectiveApplicationNote of the corresponding type.
     *
     * @return void
     */

    public function test_commit_creates_notes_for_non_blank_comments(): void
    {
        $import = new ProspectiveApplicationImport();
        $import->importApplication($this->applicationRecord([
            'VC_Comments__c' => 'VC says hello.',
            'Why_Ranger_Comments__c' => 'Why-Ranger says hi.',
        ]), $this->contactRecord(), false, true);

        $this->assertCount(1, $import->newApplications);
        $applicationId = $import->newApplications[0]->id;

        $this->assertDatabaseHas('prospective_application_note', [
            'prospective_application_id' => $applicationId,
            'type' => ProspectiveApplicationNote::TYPE_VC,
            'note' => 'VC says hello.',
        ]);
        $this->assertDatabaseHas('prospective_application_note', [
            'prospective_application_id' => $applicationId,
            'type' => ProspectiveApplicationNote::TYPE_VC_COMMENT,
            'note' => 'Why-Ranger says hi.',
        ]);
    }

    /**
     * On a create failure, the persisted ErrorLog record carries only non-PII
     * identifiers and a sanitized exception summary -- not the whole $row (which
     * carries email/address/phone/etc.) and not the raw exception message (which,
     * e.g. for a failed insert, can itself embed field values). The raw message is
     * still surfaced via api_error_message, which is only shown to the VC/ADMIN
     * caller who already owns this record's PII.
     *
     * @return void
     */

    public function test_create_failure_logs_only_non_pii_identifiers_to_error_log(): void
    {
        $shouldThrow = true;
        ProspectiveApplicationLog::creating(function () use (&$shouldThrow) {
            if ($shouldThrow) {
                throw new RuntimeException('forced failure for pii-test@example.com');
            }
        });

        $import = new ProspectiveApplicationImport();
        $import->importApplication($this->applicationRecord([
            'Id' => 'a01',
            'Name' => 'R-9101',
            'Contact_Email__c' => 'pii-test@example.com',
        ]), $this->contactRecord(), false, true);

        $this->assertCount(1, $import->creationFailures);
        $this->assertStringContainsString('pii-test@example.com', $import->creationFailures[0]->api_error_message);

        $log = ErrorLog::where('error_type', 'prospective-application-create-failure')->firstOrFail();
        $this->assertStringNotContainsString('pii-test@example.com', $log->data);

        $data = json_decode($log->data, true);
        $this->assertSame([
            'salesforce_id' => 'a01',
            'salesforce_name' => 'R-9101',
            'api_error' => ProspectiveApplication::API_ERROR_CREATE_FAILURE,
        ], $data['record']);
        $this->assertSame(RuntimeException::class, $data['exception']['message']);
    }

    /**
     * An Attended_Burning_Man_Twice__c value with no entry in EXPERIENCE_MAP defaults
     * the record to EXPERIENCE_NONE instead of raising an undefined-array-key error.
     *
     * @return void
     */

    public function test_unmapped_experience_defaults_to_none(): void
    {
        $import = new ProspectiveApplicationImport();
        $import->importApplication($this->applicationRecord([
            'Attended_Burning_Man_Twice__c' => 'Some Unrecognized Value',
        ]), $this->contactRecord(), false, false);

        $this->assertCount(1, $import->newApplications);
        $this->assertEquals(ProspectiveApplication::EXPERIENCE_NONE, $import->newApplications[0]->experience);
    }

    /**
     * A page of multiple applications results in exactly ONE batched contact SOQL query
     * (not one per application, per R5), and each record's inaccessible/blank/success
     * outcome is still determined correctly from within that single batch.
     *
     * @return void
     */

    public function test_import_for_year_batches_contact_lookups_into_a_single_query_per_page(): void
    {
        $fake = $this->bindFakeConnector(
            // Page 1: three application rows.
            $this->queryEnvelope([
                $this->applicationRecord(['Id' => 'a01', 'Name' => 'R-301']),
                $this->applicationRecord(['Id' => 'a02', 'Name' => 'R-302']),
                $this->applicationRecord(['Id' => 'a03', 'Name' => 'R-303']),
            ]),
            // Batched contact lookup for page 1's three Ids: a01 is accessible and
            // non-blank, a02 is absent from the response entirely (inaccessible), and
            // a03 is present but every mapped field is blank.
            $this->queryEnvelope([
                $this->contactRecord('a01'),
                (object) ['Id' => 'a03', 'Ranger_Info__r' => (object) ['Years_Attended_Burning_Man__c' => '']],
            ]),
            // Page 2: empty, ends pagination.
            $this->queryEnvelope([]),
        );

        $import = new ProspectiveApplicationImport();
        $import->importForYear((int) date('Y'), false);

        $contactQueries = array_values(array_filter($fake->queries, fn (string $q): bool => str_contains($q, 'Ranger_Info__r.Id')));
        $this->assertCount(1, $contactQueries);
        $this->assertStringContainsString("Id IN ('a01','a02','a03')", $contactQueries[0]);

        $this->assertCount(1, $import->newApplications);
        $this->assertEquals('R-301', $import->newApplications[0]->salesforce_name);

        $this->assertCount(2, $import->queryFailures);
        $byName = collect($import->queryFailures)->keyBy('salesforce_name');
        $this->assertEquals(ProspectiveApplication::API_ERROR_CONTACT_INACCESSIBLE, $byName['R-302']->api_error);
        $this->assertEquals(ProspectiveApplication::API_ERROR_CONTACT_BLANK, $byName['R-303']->api_error);
    }

    /**
     * If the batched contact query itself fails (a Salesforce error, not a per-record
     * access restriction), every application in that page degrades to contact-inaccessible
     * rather than throwing and aborting the import.
     *
     * @return void
     */

    public function test_failed_batch_contact_query_marks_whole_page_as_inaccessible(): void
    {
        $this->bindFakeConnector(
            $this->queryEnvelope([
                $this->applicationRecord(['Id' => 'a01', 'Name' => 'R-401']),
                $this->applicationRecord(['Id' => 'a02', 'Name' => 'R-402']),
            ]),
            false, // the batched contact query itself fails
            $this->queryEnvelope([]), // page 2: empty, ends pagination
        );

        $import = new ProspectiveApplicationImport();
        $import->importForYear((int) date('Y'), false);

        $this->assertEmpty($import->newApplications);
        $this->assertCount(2, $import->queryFailures);
        foreach ($import->queryFailures as $failure) {
            $this->assertEquals(ProspectiveApplication::API_ERROR_CONTACT_INACCESSIBLE, $failure->api_error);
        }
    }

    /**
     * queryContactRecords() quotes/escapes each Id defensively when building the IN (...)
     * clause, keys its result map by each record's Id, and returns an empty map without
     * issuing a query when given no Ids.
     *
     * @return void
     */

    public function test_query_contact_records_escapes_ids_and_skips_query_when_empty(): void
    {
        $fake = $this->bindFakeConnector($this->queryEnvelope([$this->contactRecord("a'01")]));

        $import = new ProspectiveApplicationImport();
        $map = $import->queryContactRecords(["a'01"]);

        $this->assertCount(1, $fake->queries);
        $this->assertStringContainsString("Id IN ('" . addslashes("a'01") . "')", $fake->queries[0]);
        $this->assertArrayHasKey("a'01", $map);

        $this->assertSame([], $import->queryContactRecords([]));
        $this->assertCount(1, $fake->queries);
    }
}

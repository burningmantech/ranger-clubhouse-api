<?php

namespace Tests\Feature;

use App\Lib\SalesforceConnector;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeSalesforceConnector;
use Tests\TestCase;

class ClubhouseImportPastApplicationsCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bind a fake connector that authenticates successfully and answers every
     * soqlQuery() call with the given result.
     *
     * @param object|false $queryResult
     * @return FakeSalesforceConnector
     */

    private function bindFakeConnector(object|false $queryResult): FakeSalesforceConnector
    {
        $fake = new FakeSalesforceConnector();
        $fake->authResult = true;
        $fake->queryResult = $queryResult;
        $this->app->instance(SalesforceConnector::class, $fake);

        return $fake;
    }

    /**
     * When every year's import completes without a Salesforce query failure, the
     * command reports success.
     *
     * @return void
     */

    public function test_handle_returns_success_when_no_years_have_query_errors(): void
    {
        $this->bindFakeConnector((object) ['totalSize' => 0, 'records' => []]);

        $this->artisan('clubhouse:import-past-applications')
            ->assertExitCode(Command::SUCCESS);
    }

    /**
     * When a year's page query fails, importForYear() sets errorMessage but previously
     * the command silently swallowed it and reported the same result as a clean run.
     * The command must now surface the failure and exit non-zero instead of silently
     * truncating the import.
     *
     * @return void
     */

    public function test_handle_reports_error_and_returns_failure_when_a_years_query_fails(): void
    {
        $this->bindFakeConnector(false);

        $this->artisan('clubhouse:import-past-applications')
            ->expectsOutputToContain('Salesforce API query failed')
            ->assertExitCode(Command::FAILURE);
    }

    /**
     * When Salesforce authentication fails, the command must return a failure exit
     * code from handle() rather than terminating the whole PHP process via exit().
     *
     * @return void
     */

    public function test_handle_returns_failure_when_salesforce_auth_fails(): void
    {
        $fake = new FakeSalesforceConnector();
        $fake->authResult = false;
        $this->app->instance(SalesforceConnector::class, $fake);

        $this->artisan('clubhouse:import-past-applications')
            ->expectsOutputToContain('Salesforce authentication failure')
            ->assertExitCode(Command::FAILURE);
    }

    /**
     * A contact-lookup failure (WITH USER_MODE query, so it does not set errorMessage)
     * records an entry in queryFailures without ever setting $import->errorMessage.
     * Previously the command only inspected errorMessage, so this case was reported
     * as a clean success even though an application failed to import.
     *
     * @return void
     */

    public function test_handle_returns_failure_when_import_has_query_failures_without_error_message(): void
    {
        $applicationRecord = (object) [
            'Id' => 'a01',
            'Name' => 'R-9001',
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
            'VC_Event_Year__c' => 2015,
            'VC_Status__c' => 'Not Yet Started',
            'Why_Ranger__c' => 'To help.',
            'Why_Ranger_Comments__c' => '',
            'Regional_Ranger_Experience__c' => '',
            'Current_Regional_Callsign__c' => '',
            'Ranger_Info_Over_18_Check__c' => 'Yes',
            'Attended_Burning_Man_Twice__c' => '',
        ];

        $fake = new FakeSalesforceConnector();
        $fake->authResult = true;
        // Every list query beyond the queued ones below returns an empty page, so the
        // remaining years complete cleanly and never populate errorMessage.
        $fake->queryResult = (object) ['totalSize' => 0, 'records' => []];
        $fake->queryResults = [
            // 2015's first page: one application row.
            (object) ['totalSize' => 1, 'records' => [$applicationRecord]],
            // The batched contact lookup for that row fails. This is a WITH USER_MODE
            // query, so executeQuery() does NOT set $import->errorMessage for it.
            false,
            // 2015's second page: empty, ends pagination for that year.
            (object) ['totalSize' => 0, 'records' => []],
        ];
        $this->app->instance(SalesforceConnector::class, $fake);

        $this->artisan('clubhouse:import-past-applications')
            ->expectsOutputToContain('R-9001')
            ->assertExitCode(Command::FAILURE);
    }
}

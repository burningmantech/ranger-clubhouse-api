<?php

namespace Tests\Feature;

use App\Lib\SalesforceConnector;
use App\Models\PersonIntakeNote;
use App\Models\Role;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\Support\FakeSalesforceConnector;
use Tests\TestCase;

class SalesforceImportTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        $this->signInUser();
        $this->addRole(Role::ADMIN);

        $this->setting('SFEnableWritebacks', true);
        $this->setting('SendWelcomeEmail', false);

        Mail::fake();
    }

    /**
     * A test double setting is inserted (or replaced) directly.
     *
     * @param $name
     * @param $value
     * @return void
     */

    public function setting($name, $value): void
    {
        Setting::where('name', $name)->delete();
        Setting::insert(['name' => $name, 'value' => $value]);
    }

    /**
     * Build a Salesforce Ranger object that a PCA will classify as "ready".
     *
     * @param array $overrides
     * @return object
     */

    private function readyRecord(array $overrides = []): object
    {
        $defaults = [
            'Id' => 'a01',
            'Name' => 'R-100',
            'callsign' => 'Testing Newbie',
            'email' => 'newbie@example.com',
            'bpguid' => 'BP-100',
            'sfuid' => 'SF-100',
        ];
        $r = array_merge($defaults, $overrides);

        return (object) [
            'Id' => $r['Id'],
            'Name' => $r['Name'],
            'CH_UID__c' => '',
            'Ranger_Applicant_Type__c' => 'Prospective New Volunteer - Black Rock Ranger',
            'VC_Approved_Radio_Call_Sign__c' => $r['callsign'],
            'VC_Comments__c' => 'imported by test',
            'VC_Status__c' => 'Released to Upload',
            'Known_Rangers_Names__c' => '',
            'Known_Prospective_Volunteer_Names__c' => '',
            'Contact_Email__c' => $r['email'],
            'Ranger_Info__r' => (object) [
                'FirstName' => 'New',
                'LastName' => 'Newbie',
                'MailingStreet' => '123 Playa Rd',
                'MailingCity' => 'Gerlach',
                'MailingState' => 'NV',
                'MailingCountry' => 'US',
                'MailingPostalCode' => '89412',
                'npe01__HomeEmail__c' => $r['email'],
                'Phone' => '775-555-1212',
                'BPGUID__c' => $r['bpguid'],
                'SFUID__c' => $r['sfuid'],
                'Emergency_Contact_Name__c' => '',
                'Emergency_Contact_Phone__c' => '',
                'Emergency_Contact_Relationship__c' => '',
            ],
        ];
    }

    /**
     * Bind a fake connector returning the given records for the import query.
     *
     * @param array $records
     * @return FakeSalesforceConnector
     */

    private function bindFakeConnector(array $records): FakeSalesforceConnector
    {
        $fake = new FakeSalesforceConnector();
        $fake->queryResult = (object) ['totalSize' => count($records), 'records' => $records];
        $this->app->instance(SalesforceConnector::class, $fake);

        return $fake;
    }

    /**
     * A failed Salesforce writeback (objUpdate returns false) surfaces a warning
     * in the import response rather than being silently swallowed.
     *
     * @return void
     */

    public function test_writeback_failure_surfaces_warning_in_response(): void
    {
        $fake = $this->bindFakeConnector([$this->readyRecord()]);
        $fake->updateResult = false;
        $fake->errorMessage = 'permission denied';

        $response = $this->json('GET', 'salesforce/import', [
            'create_accounts' => true,
            'update_sf' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $account = $response->json('accounts.0');
        $this->assertEquals('succeeded', $account['status']);
        $this->assertStringContainsString('Warning', $account['message']);

        // The account was still created despite the writeback failure.
        $this->assertDatabaseHas('person', ['callsign' => 'Testing Newbie']);
    }

    /**
     * When a post-save related write throws mid-import, the whole write is rolled
     * back (no orphaned Person), the batch does not 500, and the record is
     * reported failed so the remaining records still process.
     *
     * @return void
     */

    public function test_post_save_writes_are_atomic_and_batch_survives(): void
    {
        $this->bindFakeConnector([
            $this->readyRecord(),
            $this->readyRecord([
                'Id' => 'a02',
                'Name' => 'R-101',
                'callsign' => 'Testing Second',
                'email' => 'second@example.com',
                'bpguid' => 'BP-101',
                'sfuid' => 'SF-101',
            ]),
        ]);

        // Force the in-transaction PersonIntakeNote::record write (vc_comments is
        // non-empty) to throw, after the Person has already been saved.
        PersonIntakeNote::creating(function () {
            throw new RuntimeException('intake note write failed');
        });

        $response = $this->json('GET', 'salesforce/import', [
            'create_accounts' => true,
        ]);

        // The batch completes rather than 500-ing on the bad record.
        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $accounts = $response->json('accounts');
        $this->assertCount(2, $accounts);
        foreach ($accounts as $account) {
            $this->assertEquals('failed', $account['status']);
        }

        // The Person save was rolled back with the failing related write.
        $this->assertDatabaseMissing('person', ['callsign' => 'Testing Newbie']);
        $this->assertDatabaseMissing('person', ['callsign' => 'Testing Second']);
    }
}

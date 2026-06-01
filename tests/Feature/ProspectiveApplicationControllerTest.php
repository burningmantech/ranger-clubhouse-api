<?php

namespace Tests\Feature;

use App\Lib\ProspectiveClubhouseAccountFromApplication;
use App\Mail\ProspectiveApplication\RejectUnqualifiedMail;
use App\Mail\WelcomeMail;
use App\Models\Person;
use App\Models\ProspectiveApplication;
use App\Models\ProspectiveApplicationNote;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProspectiveApplicationControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    const string VCEMAIL = 'vcemail@example.com';

    public function setUp(): void
    {
        parent::setUp();
        $this->signInUser();
        $this->addRole(Role::VC);
    }

    /*
     * Get the prospective applications
     */

    public function testIndexProspectiveApplication()
    {
        $app = ProspectiveApplication::factory()->create();

        $response = $this->json('GET', 'prospective-application');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json()['prospective_application']);
    }

    /*
     * Create a prospective application
     */

    public function testStoreProspectiveApplication()
    {
        $data = [
            'status' => ProspectiveApplication::STATUS_PENDING,
            'events_attended' => '2023;2024',
            'salesforce_name' => 'R-' . $this->faker->uuid(),
            'salesforce_id' => $this->faker->uuid(),
            'sfuid' => $this->faker->uuid(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'street' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'country' => 'US',
            'postal_code' => $this->faker->postcode(),
            'phone' => $this->faker->phoneNumber(),
            'year' => date('Y'),
            'email' => $this->faker->email(),
            'bpguid' => $this->faker->uuid(),
            'person_id' => null,
            'why_volunteer' => $this->faker->words(10, true),
            'why_volunteer_review' => $this->faker->words(5, true),
            'known_rangers' => $this->faker->words(5, true),
            'known_applicants' => $this->faker->words(5, true),
            'is_over_18' => true,
            'handles' => $this->faker->words(5, true),
            'approved_handle' => $this->faker->words(2, true),
            'rejected_handles' => null,
            'regional_experience' => $this->faker->words(5, true),
            'regional_callsign' => $this->faker->words(2, true),
            'experience' => ProspectiveApplication::EXPERIENCE_BRC2,
            'emergency_contact' => $this->faker->words(10, true),
            'assigned_person_id' => null,
            'updated_by_person_id' => null,
            'updated_by_person_at' => null,
        ];

        $response = $this->json('POST', 'prospective-application', [
            'prospective_application' => $data
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('prospective_application', $data);
    }

    /*
     * Update a prospective application
     */

    public function testUpdateProspectiveApplication()
    {
        $app = ProspectiveApplication::factory()->create();

        $response = $this->json('PATCH', "prospective-application/{$app->id}", [
            'prospective_application' => ['first_name' => 'hubcapper']
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('prospective_application', ['id' => $app->id, 'first_name' => 'hubcapper']);
    }

    /**
     * Test show a record
     */

    public function testShowProspectiveApplication()
    {
        $app = ProspectiveApplication::factory()->create();

        $response = $this->json('GET', "prospective-application/{$app->id}");
        $response->assertStatus(200);
        $response->assertJson([
            'prospective_application' => [
                'id' => $app->id,
                'first_name' => $app->first_name,
                'last_name' => $app->last_name,
                'bpguid' => $app->bpguid,
            ]
        ]);
    }

    /**
     * Test searching for applications
     */

    const string SEARCH_URL = 'prospective-application/search';

    public function testSearchProspectiveApplications()
    {
        // No results
        $response = $this->json('GET', self::SEARCH_URL, [ 'query' => 'blah blah']);
        $response->assertStatus(200);
        $response->assertJson([]);

        // By BPGUID
        $this->searchBy(fn ($app) => $app->bpguid);
        // By application id (A-nnnn)
        $this->searchBy(fn ($app) => 'A-'.$app->id);
        // By the Salesforce record name (R-NNNN)
        $this->searchBy(fn ($app) => $app->salesforce_name);
        // By an email address
        $this->searchBy(fn ($app) => $app->email);
        // By first + last name
        $this->searchBy(fn  ($app) => $app->first_name . ' ' . $app->last_name);
    }

    /**
     * Run the search
     *
     * @param callable $query
     * @return void
     */

    private function searchBy(callable $query): void
    {
        $app = ProspectiveApplication::factory()->create();
        $response = $this->json('GET', self::SEARCH_URL,
            [
                'query' => $query($app)
            ]);

        $response->assertJson([
            [
                'id' => $app->id
            ]
        ]);
    }

    /*
     * Delete a record
     */

    public function testDeleteProspectiveApplication()
    {
        $app = ProspectiveApplication::factory()->create();
        $id = $app->id;

        $response = $this->json('DELETE', "prospective-application/{$id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('prospective_application', ['id' => $id]);
    }

    /**
     * Test related applications.
     *
     * @return void
     */

    public function testRelatedApplications()
    {
        $app = ProspectiveApplication::factory()->create();
        $related = ProspectiveApplication::factory()->create([
            'bpguid' => $app->bpguid,
        ]);

        $response = $this->json('GET', "prospective-application/{$app->id}/related");
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'applications');
        $response->assertJson([
            'applications' => [
                [
                    'id' => $related->id
                ]
            ]
        ]);

        // Check the application has no related.
        $hasNone = ProspectiveApplication::factory()->create();
        $response = $this->json('GET', "prospective-application/{$hasNone->id}/related");
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'applications');
    }

    /*
     * Ensure emails are sent
     */

    public function testEmails()
    {
        $app = ProspectiveApplication::factory()->create();
        $this->setting('VCEmail', self::VCEMAIL);

        Mail::fake();

        $response = $this->json('POST', "prospective-application/{$app->id}/status", [
            'status' => ProspectiveApplication::STATUS_REJECT_UNQUALIFIED,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('prospective_application', [
            'id' => $app->id,
            'status' => ProspectiveApplication::STATUS_REJECT_UNQUALIFIED
        ]);

        Mail::assertQueued(RejectUnqualifiedMail::class, function ($mail) use ($app) {
            return $mail->hasTo($app->email) && $mail->hasFrom(self::VCEMAIL);
        });
    }

    /*
     * Check an application can be turned into an account.
     */

    public function testCreateProspectives()
    {
        $create = ProspectiveApplication::factory()->create(['status' => ProspectiveApplication::STATUS_APPROVED]);
        $notCurrent = ProspectiveApplication::factory()->create([
            'status' => ProspectiveApplication::STATUS_APPROVED,
            'year' => date('Y') - 1
        ]);
        $notApproved = ProspectiveApplication::factory()->create(['status' => ProspectiveApplication::STATUS_PENDING]);

        $response = $this->json('POST', 'prospective-application/create-prospectives');
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
        $response->assertJsonCount(1, 'applications');
        $response->assertJson([
            'applications' => [
                [
                    'status' => ProspectiveClubhouseAccountFromApplication::STATUS_READY,
                    'salesforce_id' => $create->salesforce_id,
                    'bpguid' => $create->bpguid,
                ]
            ]
        ]);
    }

    /**
     * Committing an approved application creates a prospective account, flips the
     * application to created, and sends the welcome email.
     */

    public function testCommitCreatesProspectiveAccount()
    {
        Mail::fake();
        $this->setting('SendWelcomeEmail', true);

        $app = ProspectiveApplication::factory()->create([
            'status' => ProspectiveApplication::STATUS_APPROVED,
            'approved_handle' => 'Hubcap',
            'email' => 'newpnv@example.com',
        ]);

        $prospective = new ProspectiveClubhouseAccountFromApplication($app, [], false, null);
        $this->assertTrue($prospective->verifyRequiredFields());
        $prospective->checkForExisting();
        $this->assertEquals(ProspectiveClubhouseAccountFromApplication::STATUS_READY, $prospective->status);

        $prospective->createAccount();

        $this->assertEquals(ProspectiveClubhouseAccountFromApplication::STATUS_CREATED, $prospective->status);
        $this->assertNotNull($prospective->person_id);

        $this->assertDatabaseHas('person', [
            'id' => $prospective->person_id,
            'callsign' => 'Hubcap',
            'email' => 'newpnv@example.com',
            'status' => Person::PROSPECTIVE,
        ]);

        $app->refresh();
        $this->assertEquals(ProspectiveApplication::STATUS_CREATED, $app->status);
        $this->assertEquals($prospective->person_id, $app->person_id);

        Mail::assertQueued(WelcomeMail::class);
    }

    /**
     * A retired account's callsign is reassigned to the incoming applicant.
     */

    public function testCommitReassignsRetiredCallsign()
    {
        $this->setting('SendWelcomeEmail', false);

        $retired = Person::factory()->create([
            'callsign' => 'Hubcap',
            'status' => Person::RETIRED,
        ]);

        $app = ProspectiveApplication::factory()->create([
            'status' => ProspectiveApplication::STATUS_APPROVED,
            'approved_handle' => 'Hubcap',
        ]);

        $prospective = new ProspectiveClubhouseAccountFromApplication($app, [], false, null);
        $this->assertTrue($prospective->verifyRequiredFields());
        $prospective->checkForExisting();
        $this->assertEquals(ProspectiveClubhouseAccountFromApplication::STATUS_READY, $prospective->status);
        $this->assertNotNull($prospective->reassign_callsign);

        $prospective->createAccount();

        $this->assertEquals(ProspectiveClubhouseAccountFromApplication::STATUS_CREATED, $prospective->status);
        $this->assertNotEquals($retired->id, $prospective->person_id);

        // The new account holds the callsign.
        $this->assertDatabaseHas('person', [
            'id' => $prospective->person_id,
            'callsign' => 'Hubcap',
        ]);

        // The retired account no longer holds it.
        $retired->refresh();
        $this->assertNotEquals('Hubcap', $retired->callsign);
    }

    /**
     * A failed account save rolls back the already-reassigned callsign and leaves
     * the application untouched.
     */

    public function testRollsBackReassignedCallsignWhenSaveFails()
    {
        $this->setting('SendWelcomeEmail', false);

        $retired = Person::factory()->create([
            'callsign' => 'Hubcap',
            'status' => Person::RETIRED,
        ]);

        // A first name beyond Person's max:25 rule forces the new-account save to fail.
        $app = ProspectiveApplication::factory()->create([
            'status' => ProspectiveApplication::STATUS_APPROVED,
            'approved_handle' => 'Hubcap',
            'first_name' => str_repeat('a', 40),
        ]);

        $prospective = new ProspectiveClubhouseAccountFromApplication($app, [], false, null);
        $prospective->verifyRequiredFields();
        $prospective->checkForExisting();
        $this->assertEquals(ProspectiveClubhouseAccountFromApplication::STATUS_READY, $prospective->status);

        $prospective->createAccount();

        $this->assertEquals(ProspectiveClubhouseAccountFromApplication::STATUS_CREATE_FAILED, $prospective->status);

        // The reassigned callsign was rolled back: the retired person keeps it.
        $this->assertDatabaseHas('person', [
            'id' => $retired->id,
            'callsign' => 'Hubcap',
        ]);

        // The application was not flipped to created.
        $app->refresh();
        $this->assertEquals(ProspectiveApplication::STATUS_APPROVED, $app->status);
        $this->assertNull($app->person_id);
    }

    /**
     * Test add, updating, and deleting a note.
     */

    public function testNoteOperations()
    {
        $app = ProspectiveApplication::factory()->create();
        $data = [
            'note' => $this->faker->sentence(),
            'type' => ProspectiveApplicationNote::TYPE_VC,
        ];

        $response = $this->json('POST', "prospective-application/{$app->id}/note", $data);
        $response->assertStatus(200);
        $data['prospective_application_id'] = $app->id;
        $data['person_id'] = $this->user->id;
        $this->assertDatabaseHas('prospective_application_note', $data);

        $noteId = ProspectiveApplicationNote::where('prospective_application_id', $app->id)->value('id');
        $note = 'My updated note';
        $response = $this->json('PATCH', "prospective-application/{$app->id}/note", [
            'note' => $note,
            'prospective_application_note_id' => $noteId
        ]);
        $response->assertStatus(200);
        $data['note'] = $note;
        $data['id'] = $noteId;
        $this->assertDatabaseHas('prospective_application_note', $data);

        $response = $this->json('DELETE', "prospective-application/{$app->id}/note",
            ['prospective_application_note_id' => $noteId]
        );
        $response->assertStatus(200);
        $this->assertDatabaseMissing('prospective_application_note', ['prospective_application_id' => $app->id]);
    }

}

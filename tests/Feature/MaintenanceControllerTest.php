<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\BMID;
use App\Models\Person;
use App\Models\PersonMessage;
use App\Models\PersonPosition;
use App\Models\Role;

class MaintenanceControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
     * have each test have a fresh user that is logged in.
     */

    public function setUp() : void
    {
        parent::setUp();
        $this->signInUser();
        $this->addRole(Role::ADMIN);
    }

    /*
     * Test Mark All Users as off site
     */

    public function testMarkOffSite()
    {
        $person = factory(Person::class)->create([ 'on_site' => true ]);

        $response = $this->json('POST', 'maintenance/mark-off-site');
        $response->assertStatus(200);
        $response->assertJson([ 'count' => 1 ]);
        $this->assertDatabaseHas('person', [ 'id' => $person->id, 'on_site' => false ]);
    }

    /*
     * test Deauthorize assets (clear annual agreements, authorizations, etc)
     */

    public function testDeauthorizeAssets()
    {
        $person = factory(Person::class)->create([
              'vehicle_paperwork'           => true,
              'vehicle_insurance_paperwork' => true,
              'sandman_affidavit'           => true,
              'asset_authorized'            => true
          ]);

        $response = $this->json('POST', 'maintenance/deauthorize-assets');
        $response->assertStatus(200);
        $response->assertJson([ 'count' => 1]);
        $this->assertDatabaseHas('person', [
              'id'  => $person->id,
              'vehicle_paperwork'           => false,
              'vehicle_insurance_paperwork' => false,
              'sandman_affidavit'           => false,
              'asset_authorized'            => false
          ]);
    }

    /*
     * test reset PNVs to past prospectives
     */

    public function testResetPNVs()
    {
        $prospective = factory(Person::class)->create([
             'status'   => 'prospective',
             'first_name'   => 'Alfred',
             'last_name'    => 'Newman',
         ]);

        $year = date('Y') % 100;

        $response = $this->json('POST', 'maintenance/reset-pnvs');
        $response->assertStatus(200);

        $response->assertJson([ 'people'   => [
            [
                'id'       => $prospective->id,
                'status'   => 'prospective',        // report on old status
                'callsign_reset' => 'NewmanA'.$year,
            ]
        ]]);

        $this->assertDatabaseHas('person', [
            'id'       => $prospective->id,
            'status'   => 'past prospective',
            'callsign' => 'NewmanA'.$year,
        ]);
    }

    /*
     * test reset Past Prospecitves
     */

    public function testResetPastProspectives()
    {
        $pp = factory(Person::class)->create([
              'status'   => 'past prospective',
              'first_name'   => 'Jabber',
              'last_name'    => 'Wocky',
              'callsign'    => 'JabberWocky',
              'callsign_approved' => true
          ]);

        $year = date('Y') % 100;

        $response = $this->json('POST', 'maintenance/reset-past-prospectives');
        $response->assertStatus(200);

        $response->assertJson([ 'people'   => [
             [
                 'id'       => $pp->id,
                 'status'   => 'past prospective',
                 'callsign' => 'JabberWocky',
                 'callsign_reset' => 'WockyJ'.$year,
             ]
         ]]);

        $this->assertDatabaseHas('person', [
             'id'       => $pp->id,
             'status'   => 'past prospective',
             'callsign' => 'WockyJ'.$year,
         ]);
    }

    /*
     * Test archiving Clubhouse Messages
     */

     public function testArchiveMessages()
     {
         $user = $this->user;

         $archiveYear = date('Y') - 1;

         $archiveMessage = factory(PersonMessage::class)->create([
             'person_id'          => $user->id,
             'recipient_callsign' => $user->callsign,
             'creator_person_id'  => $user->id,
             'subject'            => 'Old News',
             'body'               => 'Whatever!',
             'timestamp'          => "$archiveYear-01-01 10:00:00",
         ]);

         $ignoreMessage = factory(PersonMessage::class)->create([
             'person_id'          => $user->id,
             'recipient_callsign' => $user->callsign,
             'creator_person_id'  => $user->id,
             'subject'            => 'Current News',
             'body'               => 'Whatever!',
             'timestamp'          => date('Y-m-d 00:00:00')
         ]);

         $response = $this->json('POST', 'maintenance/archive-messages');
         $response->assertStatus(200);
         $response->assertJson([ 'status' => 'success', 'year' => $archiveYear ]);
         $this->assertDatabaseMissing('person_message', [ 'id' => $archiveMessage->id ]);
         $this->assertDatabaseHas('person_message', [ 'id' => $ignoreMessage->id ]);
         $this->assertDatabaseHas('person_message_'.$archiveYear, [ 'person_id'  => $user->id ]);

         // Re-run the archive, which should error since the archive table already exists
         $response = $this->json('POST', 'maintenance/archive-messages');
         $response->assertStatus(200);
         $response->assertJson([ 'status' => 'archive-exists', 'year' => $archiveYear ]);

     }
}

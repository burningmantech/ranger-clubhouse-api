<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\AccessDocument;
use App\Models\Bmid;
use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\Role;
use App\Models\Slot;
use App\Models\TraineeStatus;

class BmidControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
     * have each test have a fresh user that is logged in.
     */

    public function setUp() : void
    {
        parent::setUp();
        $this->signInUser();
        $this->addRole(Role::EDIT_BMIDS);

        $this->year = current_year();

        $this->setting('TAS_BoxOfficeOpenDate', "{$this->year}-08-25 00:00");
        $this->setting('TAS_DefaultWAPDate', date('Y-08-26'));
    }

    public function createBmids()
    {
        $year = $this->year;

        /*
         * Create special BMIDS -
         *  - has a title
         *  - has a meal
         *  - has a shower
         *  - has WAP access date before the box office opens.
         */

        $personWithTitle = factory(Person::class)->create([ 'callsign' => 'Masters McTitle']);
        factory(Bmid::class)->create([ 'person_id' => $personWithTitle->id, 'title1' => 'hasTitle', 'year' => $year ]);

        $personWithMeal = factory(Person::class)->create([ 'callsign' => 'Death Eater']);
        factory(Bmid::class)->create([ 'person_id' => $personWithMeal->id, 'meals' => 'all', 'year' => $year ]);

        $personWithShower = factory(Person::class)->create([ 'callsign' => 'Wet Spot Willie']);
        factory(Bmid::class)->create([ 'person_id' => $personWithShower->id, 'showers' => true, 'year' => $year ]);

        $personWithWap = factory(Person::class)->create([ 'callsign' => 'Early Bird']);
        $wap = factory(AccessDocument::class)->create([
            'person_id'   => $personWithWap->id,
            'access_date' => "$year-08-20 00:00:00",
            'type'        => 'work_access_pass',
            'status'      => 'claimed',
        ]);

        // create an alpha
        $personAlpha = factory(Person::class)->create([ 'callsign' => 'Alpha Beta', 'status' => 'alpha' ]);

        // Person signed up for on playa shifts, and/or passed training
        $slot = factory(Slot::class)->create([
            'begins'    => "$year-08-20 00:00:00",
            'ends'      => "$year-08-20 06:00:00",
            'position_id'   => Position::DIRT,
        ]);
        $personPlaya = factory(Person::class)->create([ 'callsign' => 'Dusty Bottoms' ]);
        factory(PersonSlot::class)->create([ 'person_id' => $personPlaya->id, 'slot_id' => $slot->id ]);

        // Vet passed training
        $trainingSlot = factory(Slot::class)->create([
            'begins'    => "$year-07-20 09:45:00",
            'ends'      => "$year-07-20 17:45:00",
            'position_id'   => Position::TRAINING,
        ]);

        factory(Position::class)->create([
            'id'    => Position::TRAINING,
            'type'  => 'Training'
        ]);

        $personTrained = factory(Person::class)->create([ 'callsign' => 'Trenton Trained']);

        $traineeStatus = factory(TraineeStatus::class)->create([
            'slot_id'   => $trainingSlot->id,
            'person_id' => $personTrained->id,
            'passed'    => true,
        ]);

        $this->people = [
            'title' => $personWithTitle,
            'meal'  => $personWithMeal,
            'shower' => $personWithShower,
            'wap'   => $personWithWap,
            'alpha' => $personAlpha,
            'onplaya'   => $personPlaya,
            'trained'   => $personTrained,
        ];

        // Processed BMIDs
        foreach ([ 'submitted', 'issues' ] as $status) {
            $person = factory(Person::class)->create([ 'callsign' => "BMID $status"]);
            $bmid = factory(Bmid::class)->create([
                'person_id' => $person->id,
                'year'      => $year,
                'status'    => $status,
            ]);

            $this->people[$status] = $person;
        }
    }

    /*
     * Find (raw) BMIDs for query
     */

    public function testBmidIndex()
    {
        $person = factory(Person::class)->create();
        $year = current_year();

        $bmid = factory(Bmid::class)->create([
            'person_id' => $person->id,
            'year'      => $year,
            'title1'    => 'Title X',
            'title2'    => 'Title Y',
            'title3'    => 'Title Z'
        ]);

        $response = $this->json('GET', 'bmid', [ 'year' => $year ]);
        $response->assertStatus(200);
        $response->assertJson([
            'bmids' => [
                [
                    'person_id' => $person->id,
                    'year'      => $year,
                    'title1'    => 'Title X',
                    'title2'    => 'Title Y',
                    'title3'    => 'Title Z'
                ]
            ]
        ]);
    }

    /*
     * Find signed up BMIDs for management
     */

    public function testFindBmidsForSignedup()
    {
        $this->createBmids();

        $response = $this->json('GET', 'bmid/manage', [ 'year' => $this->year,  'filter' => 'signedup' ]);
        $response->assertStatus(200);

        $response->assertJson(
            [
            'bmids' => [
                [
                    'person_id' => $this->people['onplaya']->id,
                    'status'    => 'in_prep'
                ],
                [
                    'person_id' => $this->people['trained']->id,
                    'status'    => 'in_prep'
                ]
            ]]
        );

        $response->assertJsonCount(2, 'bmids.*.person_id');
    }

    /*
     * Find everyone who has a BMID with:
     *   - a title
     *   - showers
     *   - meals
     * OR anyone who has a WAP opening before the box office.
     */

    public function testFindBmidsForSpecial()
    {
        $this->createBmids();

        $response = $this->json('GET', 'bmid/manage', [ 'year' => $this->year,  'filter' => 'special' ]);
        $response->assertStatus(200);

        $response->assertJsonFragment([ 'person_id' => $this->people['title']->id ]);
        $response->assertJsonFragment([ 'person_id' => $this->people['meal']->id ]);
        $response->assertJsonFragment([ 'person_id' => $this->people['shower']->id ]);
        $response->assertJsonFragment([ 'person_id' => $this->people['wap']->id ]);
        $response->assertJsonCount(4, 'bmids.*.person_id');
    }

    /*
     * Find everyone who has a submitted BMID
     */

    public function testFindBmidsSubmitted()
    {
        $this->createBmids();

        $response = $this->json('GET', 'bmid/manage', [ 'year' => $this->year,  'filter' => 'submitted' ]);
        $response->assertStatus(200);

        $response->assertJsonFragment([ 'person_id' => $this->people['submitted']->id ]);
        $response->assertJsonCount(1, 'bmids.*.person_id');
    }


    /*
     * Find folks with ... issues.
     */

    public function testFindBmidsWithIssues()
    {
        $this->createBmids();

        $response = $this->json('GET', 'bmid/manage', [ 'year' => $this->year,  'filter' => 'nonprint' ]);
        $response->assertStatus(200);

        $response->assertJsonFragment([ 'person_id' => $this->people['issues']->id ]);
        $response->assertJsonCount(1, 'bmids.*.person_id');
    }

    /*
     * create a BMID
     */

    public function testCreateBmid()
    {
        $person = factory(Person::class)->create();
        $data = [
            'year'      => $this->year,
            'person_id' => $person->id,
            'title1'    => 'Lord Of The Flies',
            'title2'    => 'Head Supreme',
            'title3'    => 'Keeper of Puns',
        ];

        $response = $this->json('POST', 'bmid', [ 'bmid' => $data ]);
        $response->assertStatus(200);
        $response->assertJson([ 'bmid' => $data ]);
        $this->assertDatabaseHas('bmid', $data);
    }

    /*
     * Update a BMID
     */

    public function testUpdateBmid()
    {
        $data = [
             'year'      => $this->year,
             'person_id' => $this->user->id,
             'title1'    => 'Village Idiot',
         ];

        $bmid = factory(Bmid::class)->create($data);

        $response = $this->json('PUT', "bmid/{$bmid->id}", [ 'bmid' => [ 'title1' => 'Town Crier'] ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('bmid', [
             'id'   => $bmid->id,
             'title1' => 'Town Crier'
         ]);
    }

    /*
     * Delete a BMID
     */

    public function testDestroyBmid()
    {
        $data = [
              'year'      => $this->year,
              'person_id' => $this->user->id,
          ];

        $bmid = factory(Bmid::class)->create($data);

        $response = $this->json('DELETE', "bmid/{$bmid->id}");
        $response->assertStatus(204);
        $this->assertDatabaseMissing('bmid', [
              'id'   => $bmid->id,
          ]);
    }

    /*
     * Find a potential BMID to manage
     */

    public function testFindPotentialBmidToManage()
    {
        $person = factory(Person::class)->create();

        $response = $this->json(
             'GET',
             'bmid/manage-person',
            [ 'year' => $this->year, 'person_id' => $person->id ]
         );

        $response->assertStatus(200);
        $response->assertJson([
              'bmid' => [
                  'person_id' => $person->id,
                  'year'      => $this->year,
              ]
          ]);

        $json = json_decode($response->getContent(), true);
        $this->assertEquals(false, isset($json['bmid']['id']));
    }

    /*
     * Find an existing BMID to manage
     */

    public function testFindExistingBmidToManage()
    {
        $person = factory(Person::class)->create();
        $bmid = factory(Bmid::class)->create([ 'person_id' => $person->id, 'year' => $this->year ]);

        $response = $this->json(
              'GET',
              'bmid/manage-person',
             [ 'year' => $this->year, 'person_id' => $person->id ]
          );

        $response->assertStatus(200);
        $response->assertJson([
               'bmid' => [
                   'id'        => $bmid->id,
                   'person_id' => $person->id,
                   'year'      => $this->year,
               ]
           ]);
    }

    /*
     * Do not find any BMIDs
     */

    public function testDoNotFindBmidToManage()
    {
        $response = $this->json(
               'GET',
               'bmid/manage-person',
              [ 'year' => $this->year, 'person_id' => 999999 ]
           );

        $response->assertStatus(422);
        $response->assertJson([
               'errors' => [
                   [ 'title' => 'The selected person id is invalid.'  ]
               ]
            ]);
    }

    /*
     * Test setting BMID titles for people who have special positions
     */

    public function testSetBMIDTitles()
    {
        // No BMID should be created for a person who does not hold any special positions.
        $simple = factory(Person::class)->create();

        // BMID should be created and one title set.
        $special = factory(Person::class)->create();
        factory(PersonPosition::class)->create([ 'person_id' => $special->id, 'position_id' => Position::OOD ]);

        $response = $this->json('POST', 'bmid/set-bmid-titles');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'bmids.*.id');
        $response->assertJson([
                'bmids' => [
                    [
                        'id'       => $special->id,
                        'callsign' => $special->callsign,
                        'title1'   => 'Officer of the Day'
                    ]
                ]
            ]);

        $this->assertDatabaseHas('bmid', [ 'person_id' => $special->id, 'title1' =>  'Officer of the Day' ]);
        $this->assertDatabaseMissing('bmid', [ 'person_id' => $simple->id ]);
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\EventDate;
use App\Models\Person;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\Role;
use App\Models\Slot;

class SlotControllerTest extends TestCase
{
    use WithFaker;
    use RefreshDatabase;

    /*
     * have each test have a fresh user that is logged in,
     */

    public function setUp() : void
    {
        parent::setUp();

        $this->signInUser();
        $year = $this->year = date('Y');

        // Setup default (real world) positions
        $this->trainingPosition = factory(Position::class)->create(
            [
                'id'    => Position::DIRT_TRAINING,
                'title' => 'Training',
                'type'  => 'Training',
            ]
        );

        $this->trainingSlots = [];
        for ($i = 0; $i < 3; $i++) {
            $day                   = (25 + $i);
            $this->trainingSlots[] = factory(Slot::class)->create(
                [
                    'begins'      => date("$year-05-$day 09:45:00"),
                    'ends'        => date("$year-05-$day 17:45:00"),
                    'position_id' => Position::DIRT_TRAINING,
                    'description' => "Training #$i",
                    'signed_up'   => 0,
                    'max'         => 10,
                    'min'         => 0,
                ]
            );
        }

        factory(EventDate::class)->create([
            'event_start'   => '2019-08-25 00:00:00',
            'event_end'     => '2019-09-02 23:59:00',
            'pre_event_start' => '2019-01-01 00:00:00',
            'post_event_end' => '2019-12-31 00:00:00',
            'pre_event_slot_start' => '2019-08-15 00:00:00',
            'pre_event_slot_end' => '2019-08-23 00:00:00'
        ]);
    }

    /*
     * Obtain slots for current year
     */

    public function testIndexForCurrentYear()
    {
        $response = $this->json('GET', 'slot', [ 'year' => $this->year ]);
        $response->assertStatus(200);
        $this->assertCount(3, $response->json()['slot']);
    }

    /*
     * Do not find any slots for a past year
     */

    public function testIndexForPastYear()
    {
        $response = $this->json('GET', 'slot', [ 'year' => $this->year - 1]);
        $response->assertStatus(200);
        $this->assertCount(0, $response->json()['slot']);
    }

    /*
     * Create a slot
     */

    public function testCreateSlot()
    {
        $this->addRole(Role::EDIT_SLOTS);
        $data = [
            'begins'      => date("Y-08-01 12:00:00"),
            'ends'        => date('Y-08-02 12:00:00'),
            'max'         => 99,
            'description' => 'The Minty Training',
            'position_id' => Position::GREEN_DOT_TRAINING,
            'active'      => true
        ];

        $response = $this->json('POST', 'slot', [
            'slot' => $data
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('slot', $data);
    }

    private function buildRestrictedSlot()
    {
        return [
            'begins'      => '2019-08-19 12:00:00',
            'ends'        => '2019-08-19 13:00:00',
            'max'         => 99,
            'description' => $this->faker->text(10),
            'url'         => $this->faker->text(30),
            'position_id' => Position::DIRT,
            'active'      => true
        ];
    }

    /*
     * Fail trying to create a restricted period/position slot
     */

    public function testCreateRestrictedSlotFail()
    {
        $this->addRole(Role::EDIT_SLOTS);
        $data = $this->buildRestrictedSlot();

        $response = $this->json('POST', 'slot', [
            'slot' => $data
        ]);

        $response->assertJson([
            'errors' => [
                [
                    'title' => 'Slot is a non-training position and the start time falls within the pre-event period. Action requires Admin privileges.',
                    'source' => [
                        'pointer' => 'data/attributes/begins'
                    ]
                ]
            ]
        ]);
        $response->assertStatus(422);

        $this->assertDatabaseMissing('slot', $data);
    }

    /*
     * Allow a restricted slot to be created for admin.
     */

    public function testCreateRestrictedSlotSuccess()
    {
        $this->addRole(Role::ADMIN);
        $data = $this->buildRestrictedSlot();

        $response = $this->json('POST', 'slot', [
            'slot' => $data
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('slot', $data);
    }

    /*
     * Update a slot for allowed user
     */

    public function testSlotUpdateSuccess()
    {
        $this->addRole(Role::EDIT_SLOTS);

        $slot = $this->trainingSlots[0];

        $slot->url = $this->faker->title;

        $response = $this->json('PUT', "slot/{$slot->id}", [ 'slot' => [
            'url' => $slot->url
        ]]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('slot', [
            'id'    => $slot->id,
            'url'   => $slot->url
        ]);
    }

    /*
     * Fail a restricted slot update.
     */

    public function testRestrictedSlotUpdateFail()
    {
        $this->addRole(Role::EDIT_SLOTS);
        $slot = $this->trainingSlots[0];
        $response = $this->json('PUT', "slot/{$slot->id}", [ 'slot' =>
            [
                'position_id' => Position::DIRT,
                'begins'      => '2019-08-19 12:00:00',
                'ends'        => '2019-08-19 13:00:00',
            ]
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('slot', [
            'id'          => $slot->id,
            'position_id' => Position::DIRT,
            'begins'      => '2019-08-19 12:00:00',
            'ends'        => '2019-08-19 13:00:00',
        ]);
    }

    /*
     * Allow a restricted slot update for admin
     */

    public function testRestrictedSlotUpdateSuccess()
    {
        $this->addRole(Role::ADMIN);

        $slot = $this->trainingSlots[0];
        $response = $this->json('PUT', "slot/{$slot->id}", [ 'slot' =>
            [
                'position_id' => Position::DIRT,
                'begins'      => '2019-08-19 12:00:00',
                'ends'        => '2019-08-19 13:00:00',
            ]
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('slot', [
            'id'          => $slot->id,
            'position_id' =>  Position::DIRT,
            'begins'      => '2019-08-19 12:00:00',
        ]);
    }

    /*
     * Delete a slot
     */

    public function testDeleteSlot()
    {
        $this->addRole(Role::EDIT_SLOTS);
        $slotId = $this->trainingSlots[0]->id;

        $response = $this->json('DELETE', "slot/{$slotId}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('slot', [ 'id' => $slotId ]);
    }

    /*
     * Show sign ups for a slot
     */

    public function testPeopleInSlot()
    {
        $slotId = $this->trainingSlots[0]->id;

        $person = factory(Person::class)->create();

        factory(PersonSlot::class)->create([
             'person_id'    => $person->id,
             'slot_id'      => $slotId,
         ]);

        $response = $this->json('GET', "slot/{$slotId}/people");
        $response->assertStatus(200);
        $response->assertJson([
             'people' => [
                 [ 'id' => $person->id, 'callsign' => $person->callsign ]
            ]
        ]);
    }
}

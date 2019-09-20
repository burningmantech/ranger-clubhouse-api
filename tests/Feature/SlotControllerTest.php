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

    /*
     * Verify HQ Check In/Out Forecast Report
     */

    public function testHQForestcastReport()
    {
        $this->addRole(Role::MANAGE);

        $year = $this->year;

        for ($i = 0; $i < 3; $i++) {
            $hourStart = $i*2;
            $hourEnd = $hourStart + 1;

            $begins = date("$year-08-25 0$hourStart:00:00");
            $ends = date("$year-08-25 0$hourEnd:45:00");

            // No workers on the first day
            if ($i == 0) {
                $shift = factory(Slot::class)->create(
                    [
                        'begins'      => $begins,
                        'ends'        => $ends,
                        'position_id' => Position::HQ_WINDOW,
                        'description' => "Worker #$i",
                        'signed_up'   => 1,
                        'max'         => 4,
                        'min'         => 0,
                    ]
                );
            }


            // No short on the second day
            if ($i != 1) {
                $shift = factory(Slot::class)->create(
                    [
                        'begins'      => $begins,
                        'ends'        => $ends,
                        'position_id' => Position::HQ_SHORT,
                        'description' => "HQ Short #$i",
                        'signed_up'   => 1,
                        'max'         => 1,
                        'min'         => 1,
                    ]
                );
            }

            // No lead on the third day
            if ($i != 2) {
                $shift = factory(Slot::class)->create(
                    [
                        'begins'      => $begins,
                        'ends'        => $ends,
                        'position_id' => Position::HQ_LEAD,
                        'description' => "HQ Lead #$i",
                        'signed_up'   => 1,
                        'max'         => 1,
                        'min'         => 1,
                    ]
                );
            }

            // Add some sign ups
            $people = ($i + 1) * 4;

            $dirt = factory(Slot::class)->create(
                [
                    'begins'      => $begins,
                    'ends'        => $ends,
                    'position_id' => Position::DIRT,
                    'description' => "Dirt #$i",
                    'signed_up'   => $people,
                    'max'         => 10,
                    'min'         => 1
                ]
            );

            $visits[] = [
                'period'     => $begins,
                'checkin'    => $people,
                'windows'   => ($i ? 1 : 0),
                'shorts'    => ($i != 1 ? 1 : 0),
                'leads'     => ($i != 2 ? 1 : 0),
            ];
        }

        $response = $this->json('GET', 'slot/hq-forecast-report', [ 'year' => $year, 'interval' => 60 ]);

        $response->assertJson([
            'visits' => [
                [
                    'checkin' => 4,
                    'checkout' => 0,
                    'windows' => 1,
                    'runners' => 0,
                    'shorts' => 1,
                    'leads' => 1,
                    'period' => "$year-08-25 00:00:00"
                ],
                [
                    'checkin' => 0,
                    'checkout' => 4,
                    'windows' => 0,
                    'runners' => 0,
                    'shorts' => 0,
                    'leads' => 0,
                    'period' => "$year-08-25 01:00:00"
                ],
                [
                    'checkin' => 8,
                    'checkout' => 0,
                    'windows' => 0,
                    'runners' => 0,
                    'shorts' => 0,
                    'leads' => 1,
                    'period' => "$year-08-25 02:00:00"
                ],
                [
                    'checkin' => 0,
                    'checkout' => 8,
                    'windows' => 0,
                    'runners' => 0,
                    'shorts' => 0,
                    'leads' => 0,
                    'period' => "$year-08-25 03:00:00"
                ],
                [
                    'checkin' => 12,
                    'checkout' => 0,
                    'windows' => 0,
                    'runners' => 0,
                    'shorts' => 1,
                    'leads' => 0,
                    'period' => "$year-08-25 04:00:00"
                ],
                [
                    'checkin' => 0,
                    'checkout' => 12,
                    'windows' => 0,
                    'runners' => 0,
                    'shorts' => 0,
                    'leads' => 0,
                    'period' => "$year-08-25 05:00:00"
                ]
            ]
        ]);
    }

    /*
     * Test Schedule By Position report
     */

    public function testScheduleByPosition()
    {
        $this->addRole(Role::MANAGE);

        $year = date('Y');
        $person = factory(Person::class)->create();
        $position = factory(Position::class)->create([
            'id'    => Position::DIRT_GREEN_DOT,
            'title' => 'Dirt - Green Dot'
        ]);

        $slot = factory(Slot::class)->create([
            'position_id' => Position::DIRT_GREEN_DOT,
            'begins'      => date("$year-m-d 10:00:00"),
            'ends'        => date("$year-m-d 11:00:00"),
            'max'         => 10,
        ]);

        factory(PersonSlot::class)->create([
            'person_id' => $person->id,
            'slot_id'   => $slot->id
        ]);

        $emptySlot = factory(Slot::class)->create([
            'position_id' => Position::DIRT_GREEN_DOT,
            'begins'      => date("$year-m-d 13:00:00"),
            'ends'        => date("$year-m-d 14:00:00"),
            'max'         => 10,
        ]);

        $response = $this->json('GET', 'slot/position-schedule-report', [ 'year' => $year]);
        $response->assertStatus(200);

        $response->assertJson([
            'positions' => [
                [
                    'id'    => $position->id,
                    'title' => $position->title,
                    'slots' => [
                        [
                            'begins'    => (string) $slot->begins,
                            'ends'      => (string) $slot->ends,
                            'max'       => $slot->max,
                            'sign_ups' => [
                                [
                                    'id'    => $person->id,
                                    'callsign'  => $person->callsign
                                ]
                            ]
                        ],
                        [
                            'begins'    => (string) $emptySlot->begins,
                            'ends'      => (string) $emptySlot->ends,
                            'max'       => $emptySlot->max,
                            'sign_ups' => [ ]
                        ]
                    ]
                ]
            ]
        ]);
    }

    /*
     * Test Schedule By Callsign report
     */

    public function testScheduleByCallsign()
    {
        $this->addRole(Role::MANAGE);

        $year = date('Y');
        $person = factory(Person::class)->create();
        $position = factory(Position::class)->create([
            'id'    => Position::DIRT_GREEN_DOT,
            'title' => 'Dirt - Green Dot'
        ]);

        $slot = factory(Slot::class)->create([
            'position_id' => Position::DIRT_GREEN_DOT,
            'begins'      => date("$year-m-d 10:00:00"),
            'ends'        => date("$year-m-d 11:00:00"),
            'max'         => 10,
        ]);

        factory(PersonSlot::class)->create([
            'person_id' => $person->id,
            'slot_id'   => $slot->id
        ]);

        $response = $this->json('GET', 'slot/callsign-schedule-report', [ 'year' => $year]);
        $response->assertStatus(200);

        $response->assertJson([
            'people' => [
                [
                    'id'       => $person->id,
                    'callsign' => $person->callsign,
                    'slots'    => [
                        [
                            'position'  => [
                                'id'    => $position->id,
                                'title' => $position->title,
                            ],
                            'begins'    => (string) $slot->begins,
                            'ends'      => (string) $slot->ends,
                            'description'       => $slot->description,
                        ]
                    ]
                ]
            ]
        ]);
    }

}

<?php

namespace Tests\Feature;

use App\Models\PersonPosition;
use App\Models\Timesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

use App\Models\Role;
use App\Models\Person;
use App\Models\PersonMentor;
use App\Models\PersonStatus;
use App\Models\Position;
use App\Models\PersonSlot;
use App\Models\Slot;

class MentorControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public function setUp(): void
    {
        parent::setUp();
        $this->signInUser();
        $this->addRole(Role::MENTOR);
    }

    /*
     * Test finding all mentees (alpha, prospective) in a given year
     */

    public function testMenteesForAllPNVs()
    {
        // don't find this person
        $dontFind = factory(Person::class)->create(['callsign' => 'persona1', 'status' => Person::ACTIVE]);
        factory(PersonStatus::class)->create(['person_id' => $dontFind->id, 'new_status' => Person::ACTIVE, 'created_at' => now()]);

        $alpha = factory(Person::class)->create(['callsign' => 'persona', 'status' => Person::ALPHA]);
        factory(PersonStatus::class)->create(['person_id' => $alpha->id, 'new_status' => Person::ALPHA, 'created_at' => now()]);
        $prospective = factory(Person::class)->create(['callsign' => 'personb', 'status' => Person::PROSPECTIVE]);
        factory(PersonStatus::class)->create(['person_id' => $prospective->id, 'new_status' => Person::PROSPECTIVE, 'created_at' => now()]);

        $response = $this->json('GET', 'mentor/mentees', ['year' => current_year()]);

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'mentees.*');
        $response->assertJson([
            'mentees' => [
                [
                    'id' => $alpha->id,
                    'callsign' => $alpha->callsign
                ],
                [
                    'id' => $prospective->id,
                    'callsign' => $prospective->callsign
                ],
            ]
        ]);
    }

    /*
     * Test retrieving all Alpha position granted individuals
     */

    public function testAlphas()
    {
        $alpha = factory(Person::class)->create(['callsign' => 'persona', 'status' => Person::ALPHA]);
        factory(PersonPosition::class)->create(['person_id' => $alpha->id, 'position_id' => Position::ALPHA]);

        $response = $this->json('GET', 'mentor/alphas');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'alphas.*');
        $response->assertJson([
            'alphas' => [
                [
                    'id' => $alpha->id,
                    'callsign' => $alpha->callsign
                ]
            ]
        ]);
    }

    /*
     * Test finding all the Alpha slots with sign ups
     */

    public function testAlphaSchedule()
    {
        $alpha = factory(Person::class)->create(['callsign' => 'persona', 'status' => Person::ALPHA]);
        factory(PersonPosition::class)->create(['person_id' => $alpha->id, 'position_id' => Position::ALPHA]);

        $slot = factory(Slot::class)->create([
            'position_id' => Position::ALPHA,
            'begins' => '2020-01-01 10:00:00',
            'ends' => '2020-01-01 11:00:00',
        ]);
        factory(PersonSlot::class)->create([
            'person_id' => $alpha->id,
            'slot_id' => $slot->id
        ]);

        $response = $this->json('GET', 'mentor/alpha-schedule', ['year' => 2020]);
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'slots.*');
        $response->assertJson([
            'slots' => [
                [
                    'id' => $slot->id,
                    'begins' => (string)$slot->begins,
                    'people' => [[
                        'id' => $alpha->id
                    ]]
                ]
            ]
        ]);
    }

    /*
     * Test retrieving all mentors indicating if they are on duty or not.
     */

    public function testMentors()
    {
        $time = now();

        $offDutyMentor = factory(Person::class)->create(['callsign' => 'persona']);
        factory(PersonPosition::class)->create([
            'person_id' => $offDutyMentor->id,
            'position_id' => Position::MENTOR
        ]);

        $onDutyMentor = factory(Person::class)->create(['callsign' => 'personb']);
        factory(PersonPosition::class)->create([
            'person_id' => $onDutyMentor->id,
            'position_id' => Position::MENTOR
        ]);

        $timesheet = factory(Timesheet::class)->create([
            'on_duty' => now(),
            'position_id' => Position::MENTOR,
            'person_id' => $onDutyMentor->id
        ]);

        $response = $this->json('GET', 'mentor/mentors');
        $response->assertJsonCount(2, 'mentors.*');

        $response->assertJson([
            'mentors' => [
                [
                    'id'    => $offDutyMentor->id,
                    'working' => false
                ],
                [
                    'id' => $onDutyMentor->id,
                    'working' => true
                ]
            ]
        ]);
    }

    /*
     * Test assigning mentors to Alphas
     */

    public function testMentorAssignment()
    {
        $year = current_year();

        $noMentors = factory(Person::class)->create([ 'status' => Person::ALPHA ]);

        $haveMentors = factory(Person::class)->create([ 'status' => Person::ALPHA ]);
        $mentor1 = factory(Person::class)->create();
        $mentor2 = factory(Person::class)->create();
        factory(PersonMentor::class)->create([
            'person_id' => $haveMentors->id,
            'mentor_id' => $mentor1->id,
            'mentor_year' => $year,
            'status' => PersonMentor::PENDING
        ]);

        $response = $this->json('POST', 'mentor/mentor-assignment', [
            'assignments' => [
                [
                    // add the first mentor
                    'person_id' => $noMentors->id,
                    'status' => PersonMentor::PENDING,
                    'mentor_ids' => [ $mentor1->id ]
                ],
                [
                    // Add a second mentor, and change the status to bonked.
                    'person_id' => $haveMentors->id,
                    'status' => PersonMentor::BONK,
                    'mentor_ids' => [ $mentor1->id, $mentor2->id ]
                ]
            ]
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseHas('person_mentor', [
           'person_id' => $noMentors->id,
           'mentor_id' => $mentor1->id,
           'status' => PersonMentor::PENDING
        ]);

        $this->assertEquals(2, PersonMentor::where('person_id', $haveMentors->id)->count());
        $this->assertDatabaseHas('person_mentor', [
            'person_id' => $haveMentors->id,
            'mentor_id' => $mentor1->id,
            'status' => PersonMentor::BONK
        ]);

        $this->assertDatabaseHas('person_mentor', [
            'person_id' => $haveMentors->id,
            'mentor_id' => $mentor2->id,
            'status' => PersonMentor::BONK
        ]);
    }

    /*
     * Test the Alpha verdicts report (any Alpha that is not pending)
     */

    public function testVerdict()
    {
        $year = current_year();
        $mentor = factory(Person::class)->create();

        $passed = factory(Person::class)->create([ 'status' => 'alpha']);
        factory(PersonMentor::class)->create([
            'person_id' => $passed->id,
            'mentor_id' => $mentor->id,
            'mentor_year' => $year,
            'status' => PersonMentor::PASS
        ]);

        $pending = factory(Person::class)->create([ 'status' => 'alpha']);
        factory(PersonMentor::class)->create([
            'person_id' => $pending->id,
            'mentor_id' => $mentor->id,
            'mentor_year' => $year,
            'status' => PersonMentor::PENDING
        ]);

        $response = $this->json('GET', 'mentor/verdicts');
        $response->assertJsonCount(1, 'alphas.*.id');
        $response->assertJson([
            'alphas' =>[ [
                'id' => $passed->id,
                'callsign' => $passed->callsign,
                'mentor_status' => PersonMentor::PASS
            ]]
        ]);
    }

    /*
     * Test converting Alphas into active Rangers, or bonked.
     */

    public function testConvert()
    {
        $toActive = factory(Person::class)->create([ 'status' => Person::ALPHA ]);
        $toBonked = factory(Person::class)->create([ 'status' => Person::ALPHA ]);

        $response = $this->json('POST', 'mentor/convert', [
            'alphas' => [
                [
                    'id' => $toActive->id,
                    'status' => Person::ACTIVE
                ],
                [
                    'id' => $toBonked->id,
                    'status' => Person::BONKED
                ]
            ]
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('person', [
            'id' => $toActive->id,
            'status' => Person::ACTIVE
        ]);

        $this->assertDatabaseHas('person', [
            'id' => $toBonked->id,
            'status' => Person::BONKED
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\Slot;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\Person;
use App\Models\PersonStatus;
use App\Models\PersonIntake;
use App\Models\PersonIntakeNote;
use App\Models\TraineeStatus;
use App\Models\PersonSlot;
use App\Models\Role;
use App\Models\PersonMentor;

use App\Lib\Intake;

class IntakeControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    /*
     * have each test have a fresh user that is logged in,
     */

    public function setUp(): void
    {
        parent::setUp();

        $this->signInUser();
        $year = $this->year = (int)date('Y');
    }

    /*
     * Test index - find all prospectives / alphas in a given year with a rank.
     */

    public function testIndex()
    {
        $this->addRole(Role::INTAKE);

        $personLastYear = factory(Person::class)->create(['status' => Person::ACTIVE]);
        $personThisYear = factory(Person::class)->create(['status' => Person::ACTIVE]);

        $year = $this->year - 1;

        // Should pick up this person - was a PNV in the requested year.
        factory(PersonStatus::class)->create([
            'person_id' => $personLastYear->id,
            'old_status' => Person::AUDITOR,
            'new_status' => Person::ALPHA,
            'reason' => 'intake',
            'created_at' => "$year-01-01 00:00:00",
        ]);

        factory(PersonIntake::class)->create([
            'person_id' => $personLastYear->id,
            'mentor_rank' => Intake::BELOW_AVERAGE,
            'year' => $year,
        ]);

        // Should not pick up this person.. wasn't a PNV in the current year.
        factory(PersonStatus::class)->create([
            'person_id' => $personThisYear->id,
            'old_status' => Person::AUDITOR,
            'new_status' => Person::ALPHA,
            'reason' => 'intake',
            'created_at' => "{$this->year}-01-01 00:00:00",
        ]);

        factory(PersonIntake::class)->create([
            'person_id' => $personThisYear->id,
            'mentor_rank' => Intake::BELOW_AVERAGE,
            'year' => $this->year,
        ]);

        $response = $this->json('GET', 'intake', ['year' => $year]);
        $response->assertStatus(200);
        $this->assertCount(1, $response->json()['people']);
        $response->assertJson([
            'people' => [[
                'id' => $personLastYear->id,
                'status' => $personLastYear->status
            ]]
        ]);
    }

    /*
     * Test person history
     */

    public function testPersonHistory()
    {
        $this->addRole(Role::INTAKE);
        $year = $this->year;

        $person = factory(Person::class)->create(['status' => Person::ACTIVE]);

        factory(PersonIntake::class)->create([
            'person_id' => $person->id,
            'mentor_rank' => Intake::BELOW_AVERAGE,
            'year' => $year,
        ]);

        $training = factory(Slot::class)->create(
            [
                'begins' => date("$year-01-01 09:45:00"),
                'ends' => date("$year-01-01 17:45:00"),
                'position_id' => Position::TRAINING,
                'description' => 'Training',
                'signed_up' => 0,
                'max' => 10,
                'min' => 0,
            ]
        );

        factory(PersonSlot::class)->create([
            'slot_id' => $training->id,
            'person_id' => $person->id

        ]);

        factory(TraineeStatus::class)->create([
            'slot_id' => $training->id,
            'person_id' => $person->id,
            'passed' => true,
            'rank' => Intake::ABOVE_AVERAGE
        ]);

        $mentor = factory(Person::class)->create();
        factory(PersonMentor::class)->create([
            'person_id' => $person->id,
            'mentor_id' => $mentor->id,
            'mentor_year' => $year,
            'status' => PersonMentor::BONK
        ]);

        $response = $this->json('GET', "intake/{$person->id}/history", ['year' => $year]);
        $response->assertStatus(200);
        $response->assertJson([
            'person' => [
                'id' => $person->id,
                'status' => Person::ACTIVE,
                'pnv_history' => [
                    $year => [
                        'mentor_status' => PersonMentor::BONK,
                        'mentors' => [['id' => $mentor->id, 'callsign' => $mentor->callsign]]
                    ]
                ],
                'mentor_team' => [[
                    'year' => $year,
                    'rank' => Intake::BELOW_AVERAGE
                ]],
                'trainings' => [[
                    'slot_id' => $training->id,
                    'slot_begins' => (string)$training->begins,
                    'training_rank' => Intake::ABOVE_AVERAGE,
                    'training_passed' => 1
                ]]
            ]
        ]);
    }

    /*
     * Test appending notes and setting ranks
     */

    public function testAppendNotesAndSetRanks()
    {
        $this->addRole(Role::INTAKE);

        $person = factory(Person::class)->create();
        $year = $this->year;
        $note = 'Prospectiveses brings us tasty, tasty treats, my precious!';

        $response = $this->json('POST', "intake/{$person->id}/note", [
            'year' => $year,
            'type' => 'rrn',
            'ranking' => Intake::FLAG,
            'note' => $note

        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('person_intake', [
            'person_id' => $person->id,
            'year' => $year,
            'rrn_rank' => Intake::FLAG
        ]);

        $this->assertDatabaseHas('person_intake_note', [
            'person_id' => $person->id,
            'year' => $year,
            'type' => 'rrn',
            'note' => $note
        ]);
    }

    /*
     * Test setting and clearing black flag
     */

    public function testSetAndClearBlackFlag() {
        $this->addAdminRole();
        $person = factory(Person::class)->create();
        $year = 2019;

        $response = $this->json('POST', "intake/{$person->id}/black-flag", [
            'year' => $year,
            'black_flag' => 1
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('person_intake', [
            'person_id' => $person->id,
            'year' => $year,
            'black_flag' => 1,
        ]);

        $response = $this->json('POST', "intake/{$person->id}/black-flag", [
            'year' => 2019,
            'black_flag' => 0
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('person_intake', [
            'person_id' => $person->id,
            'year' => $year,
            'black_flag' => 0,
        ]);
    }
}

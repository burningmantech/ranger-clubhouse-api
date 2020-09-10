<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Role;

use App\Models\PersonEvent;

class PersonEventControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public function setUp(): void
    {
        parent::setUp();
        $this->signInUser();
        $this->addAdminRole();
    }

    /*
     * Get the person_event documents
     */

    public function testIndex()
    {
        $year = current_year();

        $p = PersonEvent::factory()->create(['year' => $year, 'person_id' => $this->user->id]);

        $response = $this->json('GET', 'person-event', ['year' => $year]);
        $response->assertStatus(200);
        $this->assertCount(1, $response->json()['person_event']);
    }

    /*
 * Update an existing person_event row
 */

    public function testCreatePersonEvent()
    {
        $year = current_year();
        $personId = $this->user->id;
        $id = "{$personId}-{$year}";
        $response = $this->json('PATCH', "person-event/$id", [
            'person_event' => ['signed_motorpool_agreement' => true]
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('person_event', ['person_id' => $personId, 'year' => $year, 'signed_motorpool_agreement' => true]);
    }

    /*
     * Update an existing person_event row
     */

    public function testUpdatePersonEvent()
    {
        $personId = $this->user->id;
        $year = current_year();

        $personEvent = PersonEvent::factory()->create([
            'year' => $year,
            'person_id' => $personId,
        ]);

        $id = "{$personId}-{$year}";
        $response = $this->json('PATCH', "person-event/$id", [
            'person_event' => ['org_vehicle_insurance' => true]
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('person_event', ['person_id' => $personId, 'year' => $year, 'org_vehicle_insurance' => true]);
    }

    /*
     * Delete a slot
     */

    public function testDeletePersonEvent()
    {
        $personId = $this->user->id;
        $year = current_year();
        PersonEvent::factory()->create(['year' => $year, 'person_id' => $personId]);

        $response = $this->json('DELETE', "person-event/{$personId}-{$year}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('person_event', ['year' => $year, 'person_id' => $personId]);
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\Person;
use App\Models\Vehicle;

class VehicleControllerTest extends TestCase
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
     * Get the vehicle documents
     */

    public function testIndex()
    {
        $year = current_year();

        factory(Vehicle::class)->create(['person_id' => $this->user->id, 'event_year' => $year]);
        $response = $this->json('GET', 'vehicle', ['event_year' => $year]);
        $response->assertStatus(200);
        $this->assertCount(1, $response->json()['vehicle']);
    }

    /*
     * Create a vehicle document
     */

    public function testCreateVehicle()
    {
        $data = [
            'person_id' => $this->user->id,
            'type' => 'personal',
            'event_year' => current_year(),
            'vehicle_year' => '2019',
            'vehicle_make' => 'ford',
            'vehicle_model' => 'big svu',
            'vehicle_color' => 'khaki',
            'license_number' => 'ALL_UP_IN_YOUR_GRILL',
            'license_state' => 'NV',
        ];

        $response = $this->json('POST', 'vehicle', ['vehicle' => $data]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('vehicle', $data);
    }

    /*
     * Update a vehicle document
     */

    public function testUpdateVehicle()
    {
        $vehicle = factory(Vehicle::class)->create(['person_id' => $this->user->id]);

        $response = $this->json('PATCH', "vehicle/{$vehicle->id}", [
            'vehicle' => ['license_number' => 'YODAWG!']
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('vehicle', ['id' => $vehicle->id, 'license_number' => 'YODAWG!']);
    }

    /*
     * Delete a slot
     */

    public function testDeleteVehicle()
    {
        $vehicle = factory(Vehicle::class)->create();
        $vehicleId = $vehicle->id;

        $response = $this->json('DELETE', "vehicle/{$vehicleId}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('vehicle', ['id' => $vehicleId]);
    }

    public function testPreventDuplicateLicense()
    {
        $existing = factory(Vehicle::class)->create(['person_id' => $this->user->id]);

        $duplicate = [
            'person_id' => $this->user->id,
            'event_year' => $existing->event_year,
            'vehicle_year' => 2019,
            'vehicle_make' => 'ford',
            'vehicle_model' => 'big suv',
            'vehicle_color' => 'khaki',
            'license_number' => $existing->license_number,
            'license_state' => $existing->license_state,
        ];

        $response = $this->json('POST', 'vehicle', [
            'vehicle' => $duplicate
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'errors' => [
                [
                    'source' => ['pointer' => '/data/attributes/license_number']
                ]
            ]
        ]);
        $this->assertDatabaseMissing('vehicle', $duplicate);
    }
}

<?php

namespace Tests\Feature;

use App\Mail\VehiclePendingMail;
use App\Models\Person;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ClubhouseVehiclePendingCommandTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        $this->setting('VehiclePendingEmail', 'reviewer@example.com');

        Mail::fake();
    }

    /**
     * A pending vehicle whose owner (person) record no longer exists must be
     * skipped rather than crashing the command, and the remaining valid
     * pending vehicles must still be emailed.
     *
     * @return void
     */

    public function test_handle_skips_vehicle_with_missing_person_and_emails_the_rest(): void
    {
        $year = current_year();

        $owner = Person::factory()->create();
        $validVehicle = Vehicle::factory()->create([
            'person_id' => $owner->id,
            'event_year' => $year,
        ]);

        // Simulate a vehicle whose owner account was deleted: person_id points
        // to a person row that no longer exists.
        $orphanVehicle = Vehicle::factory()->create([
            'person_id' => 99999999,
            'event_year' => $year,
        ]);

        $this->artisan('clubhouse:vehicle-pending')
            ->assertExitCode(Command::SUCCESS);

        Mail::assertQueued(VehiclePendingMail::class, function (VehiclePendingMail $mail) use ($validVehicle, $orphanVehicle) {
            $ids = $mail->pending->pluck('id')->all();
            return in_array($validVehicle->id, $ids, true)
                && !in_array($orphanVehicle->id, $ids, true);
        });
    }
}

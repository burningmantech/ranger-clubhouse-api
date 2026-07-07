<?php

namespace Tests\Feature;

use App\Models\Swag;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubhouseCreateShirtSwagCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The command references Swag::TYPE_ORG_PIN when creating the B.M. service
     * pin records. Previously that constant did not exist, so the command
     * crashed with an Error on its final loop.
     *
     * @return void
     */

    public function test_handle_creates_org_pin_records_without_crashing(): void
    {
        $this->artisan('clubhouse:create-shirt-swag')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('swag', [
            'title' => '10-Year B.M. Service Pin',
            'type' => Swag::TYPE_ORG_PIN,
        ]);
    }

    /**
     * Running the command twice must not duplicate rows - each Swag record
     * should only be created once, matched on title and type.
     *
     * @return void
     */

    public function test_handle_is_idempotent_when_run_more_than_once(): void
    {
        $this->artisan('clubhouse:create-shirt-swag')->assertExitCode(Command::SUCCESS);
        $countAfterFirstRun = Swag::query()->count();

        $this->artisan('clubhouse:create-shirt-swag')->assertExitCode(Command::SUCCESS);
        $countAfterSecondRun = Swag::query()->count();

        $this->assertSame($countAfterFirstRun, $countAfterSecondRun);
    }
}

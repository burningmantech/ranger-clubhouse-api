<?php

namespace Tests\Feature;

use App\Console\Commands\ClubhouseCreateTrainingDatabase;
use App\Models\Person;
use App\Models\Position;
use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubhouseCreateTrainingDatabaseCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The Ground Hog Day guard must read the cached `clubhouse.GroundhogDayTime`
     * config value, not the raw environment variable, so it still fails
     * correctly under cached config. A stale/unrelated environment variable
     * must not fool the guard into proceeding past it (and into shelling out).
     */
    public function test_handle_fails_before_shelling_out_when_config_time_is_unset(): void
    {
        config(['clubhouse.GroundhogDayTime' => null]);
        putenv('RANGER_CLUBHOUSE_GROUNDHOG_DAY_TIME=2026-02-01 00:00:00');

        $this->artisan('clubhouse:create-training-db')
            ->expectsOutputToContain('environment variable not set')
            ->assertExitCode(1);

        putenv('RANGER_CLUBHOUSE_GROUNDHOG_DAY_TIME');
    }

    /**
     * trainActives() bulk-inserts a person_slot row for every active person
     * into the catch-all slot, but the slot's denormalized `signed_up` counter
     * must reflect that count afterward instead of staying at 0.
     */
    public function test_train_actives_updates_slot_signed_up_count(): void
    {
        Position::factory()->create([
            'id' => Position::TRAINING,
            'title' => 'Training',
            'type' => 'Training',
        ]);

        $activeCount = 3;
        Person::factory()->count($activeCount)->create(['status' => Person::ACTIVE]);
        Person::factory()->create(['status' => Person::INACTIVE]);

        $command = new ClubhouseCreateTrainingDatabase();
        $command->trainActives((int)date('Y'));

        $slot = Slot::where('description', 'Ground Hog Day Catch All')->firstOrFail();

        $this->assertSame($activeCount, (int)$slot->signed_up);
    }
}

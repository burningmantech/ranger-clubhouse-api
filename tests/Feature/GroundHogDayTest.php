<?php

namespace Tests\Feature;

use App\Lib\GroundHogDay;
use App\Models\Person;
use App\Models\PersonStatus;
use App\Models\Position;
use App\Models\Setting;
use App\Models\Slot;
use App\Models\Timesheet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GroundHogDayTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The settings that GroundHogDay::build() expects to already exist. build() loads
     * each via Setting::find() and writes to it, so they must be seeded or build() fatals.
     *
     * @var array<int, string>
     */
    const array REQUIRED_SETTINGS = [
        'RadioCheckoutAgreementEnabled',
        'MotorPoolProtocolEnabled',
        'EventManagementOnPlayaEnabled',
        'RadioInfoAvailable',
        'TimesheetCorrectionEnable',
        'BroadcastClubhouseSandbox',
        'BroadcastMailSandbox',
        'BroadcastSMSSandbox',
        'HQWindowInterfaceEnabled',
        'DashboardPeriod',
    ];

    /**
     * Seed the settings build() mutates so it can run end-to-end.
     */
    private function seedRequiredSettings(): void
    {
        foreach (self::REQUIRED_SETTINGS as $name) {
            Setting::insert(['name' => $name, 'value' => 'false']);
        }
    }

    /**
     * build() must delete position_credit and asset rows dated in years AFTER the
     * Ground Hog Day year, while leaving same-year and earlier-year rows untouched.
     *
     * Regression: the position_credit and asset cleanup queries were missing their
     * ->delete() call, so future-dated rows were never removed.
     */
    public function testBuildRemovesFutureDatedPositionCreditAndAssetRows(): void
    {
        $this->seedRequiredSettings();

        $ghdYear = 2022;
        $ghd = "{$ghdYear}-09-01 12:00:00";

        $futurePositionCreditId = DB::table('position_credit')->insertGetId([
            'position_id' => Position::DIRT,
            'start_time' => ($ghdYear + 1) . '-08-15 00:00:00',
            'end_time' => ($ghdYear + 1) . '-09-02 00:00:00',
            'credits_per_hour' => 1.00,
            'description' => 'future',
        ]);

        $currentPositionCreditId = DB::table('position_credit')->insertGetId([
            'position_id' => Position::DIRT,
            'start_time' => "{$ghdYear}-08-15 00:00:00",
            'end_time' => "{$ghdYear}-09-02 00:00:00",
            'credits_per_hour' => 1.00,
            'description' => 'current',
        ]);

        $pastPositionCreditId = DB::table('position_credit')->insertGetId([
            'position_id' => Position::DIRT,
            'start_time' => ($ghdYear - 1) . '-08-15 00:00:00',
            'end_time' => ($ghdYear - 1) . '-09-02 00:00:00',
            'credits_per_hour' => 1.00,
            'description' => 'past',
        ]);

        $futureAssetId = DB::table('asset')->insertGetId([
            'type' => 'radio',
            'barcode' => 'FUTURE-1',
            'year' => $ghdYear + 1,
            'created_at' => ($ghdYear + 1) . '-01-15 00:00:00',
        ]);

        $currentAssetId = DB::table('asset')->insertGetId([
            'type' => 'radio',
            'barcode' => 'CURRENT-1',
            'year' => $ghdYear,
            'created_at' => "{$ghdYear}-08-15 00:00:00",
        ]);

        $pastAssetId = DB::table('asset')->insertGetId([
            'type' => 'radio',
            'barcode' => 'PAST-1',
            'year' => $ghdYear - 1,
            'created_at' => ($ghdYear - 1) . '-08-15 00:00:00',
        ]);

        GroundHogDay::build($ghd);

        // Future-dated rows must be deleted.
        $this->assertDatabaseMissing('position_credit', ['id' => $futurePositionCreditId]);
        $this->assertDatabaseMissing('asset', ['id' => $futureAssetId]);

        // Same-year and earlier-year rows must survive.
        $this->assertDatabaseHas('position_credit', ['id' => $currentPositionCreditId]);
        $this->assertDatabaseHas('position_credit', ['id' => $pastPositionCreditId]);
        $this->assertDatabaseHas('asset', ['id' => $currentAssetId]);
        $this->assertDatabaseHas('asset', ['id' => $pastAssetId]);
    }

    /**
     * setupMentorTrainingData() must delete every open (off_duty IS NULL) timesheet
     * belonging to any of the Alpha people, not merely the first one.
     *
     * Regression: the query previously used where('person_id', $peopleIds) passing an
     * array as a scalar value, which silently matched only the first id. The fix uses
     * whereIntegerInRaw('person_id', $peopleIds) so all are deleted.
     */
    public function testSetupMentorTrainingDataDeletesAllOpenAlphaTimesheets(): void
    {
        $ghdYear = 2022;
        $ghd = "{$ghdYear}-09-03 12:00:00"; // 2022-09-03 is a Saturday.

        // Two Alpha people promoted in the GHD year.
        $alphaOne = Person::factory()->create(['status' => Person::ALPHA]);
        $alphaTwo = Person::factory()->create(['status' => Person::ALPHA]);

        foreach ([$alphaOne, $alphaTwo] as $alpha) {
            PersonStatus::create([
                'person_id' => $alpha->id,
                'old_status' => Person::PROSPECTIVE,
                'new_status' => Person::ALPHA,
                'person_source_id' => $alpha->id,
                'created_at' => "{$ghdYear}-08-01 00:00:00",
            ]);
        }

        // An Alpha shift slot that lands on a Saturday in the GHD year.
        $saturday = "{$ghdYear}-09-03";
        Slot::factory()->create([
            'position_id' => Position::ALPHA,
            'begins' => "{$saturday} 09:00:00",
            'ends' => "{$saturday} 19:00:00",
        ]);

        // Open Alpha timesheets (off_duty NULL) on the Saturday for BOTH people.
        $openOne = Timesheet::create([
            'person_id' => $alphaOne->id,
            'position_id' => Position::ALPHA,
            'on_duty' => "{$saturday} 09:00:00",
            'off_duty' => null,
        ]);

        $openTwo = Timesheet::create([
            'person_id' => $alphaTwo->id,
            'position_id' => Position::ALPHA,
            'on_duty' => "{$saturday} 09:00:00",
            'off_duty' => null,
        ]);

        GroundHogDay::setupMentorTrainingData($ghd);

        // BOTH open timesheets must be gone, not just the first.
        $this->assertDatabaseMissing('timesheet', ['id' => $openOne->id]);
        $this->assertDatabaseMissing('timesheet', ['id' => $openTwo->id]);
    }
}

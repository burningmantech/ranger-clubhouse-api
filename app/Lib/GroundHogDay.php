<?php

namespace App\Lib;

use App\Models\Person;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\Setting;
use App\Models\Slot;
use App\Models\Timesheet;
use App\Models\TraineeStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GroundHogDay
{
    const string ENVIRONMENT_VAR = 'RANGER_CLUBHOUSE_GROUNDHOG_DAY_TIME';

    /**
     * Convert the current database into a Ground Hog Day database
     *
     * @param string $groundHogDay
     * @return void
     */

    public static function build(string $groundHogDay): void
    {
        $year = Carbon::parse($groundHogDay)->year;

        // Mark everyone on site who had a timesheet or was scheduled to work as on site and signed paperwork
        $peopleIds = DB::table('person')
            ->select('person.id')
            ->where(function ($q) use ($year) {
                $start = "{$year}-08-20";
                $end = "{$year}-09-10";
                $q->whereRaw("EXISTS (SELECT 1 FROM slot INNER JOIN person_slot ON person_slot.slot_id=slot.id WHERE (slot.begins >= '$start' AND slot.ends <= '$end') AND person_slot.person_id=person.id LIMIT 1)");
                $q->orWhereRaw("EXISTS (SELECT 1 FROM timesheet WHERE YEAR(timesheet.on_duty)=$year AND timesheet.person_id=person.id LIMIT 1)");
            })
            ->pluck('id');

        // Kill anytime sheets in the future
        DB::table('timesheet')->where('on_duty', '>', $groundHogDay)->delete();
        // Mark timesheets ending after groundhog day as still on duty
        DB::table('timesheet')->where('off_duty', '>', $groundHogDay)->update(['off_duty' => null, 'review_status' => Timesheet::STATUS_UNVERIFIED]);

        // Remove any timesheet logs after groundhog day
        DB::table('timesheet_log')->where('created_at', '>=', $groundHogDay)->delete();
        DB::table('timesheet_missing')->where('created_at', '>=', $groundHogDay)->delete();

        // Clear out all slots in future years
        $slotIds = DB::table('slot')->select('id')->where('begins_year', '>', $year)->get()->pluck('id')->toArray();
        if (!empty($slotIds)) {
            // kill future year signups
            DB::table('person_slot')->whereIntegerInRaw('slot_id', $slotIds)->delete();
        }
        DB::table('slot')->where('begins_year', '>', $year)->delete();

        DB::table('position_credit')->whereYear('start_time', '>', $year);

        // Remove all future training info
        DB::table('trainee_status')->whereIntegerInRaw('slot_id', $slotIds)->delete();

        // Kill all assets
        DB::table('asset')->whereYear('created_at', '>', $year);

        // Mark some assets as being checked out
        DB::table('asset_person')->where('checked_out', '>', $groundHogDay)->delete();
        DB::table('asset_person')->where('checked_in', '>=', $groundHogDay)->update([
            'checked_in' => null,
            'check_in_person_id' => null
        ]);

        DB::table('person')->whereIntegerInRaw('id', $peopleIds)
            ->update([
                'on_site' => true,
                'behavioral_agreement' => true,
            ]);

        $personEvents = [];
        foreach ($peopleIds as $id) {
            $personEvents[] = [
                'person_id' => $id,
                'year' => $year,
                'signed_motorpool_agreement' => true,
                'asset_authorized' => true
            ];
        }
        DB::table('person_event')->insertOrIgnore($personEvents);

        DB::table('person_event')->where('year', $year)
            ->update([
                'signed_motorpool_agreement' => true,
                'asset_authorized' => true
            ]);

        // Setup an announcement

        DB::table('motd')->insert([
            'subject' => 'Welcome to ' . date('l, F jS Y', strtotime($groundHogDay)) . '!',
            'message' => 'Remember the temporal prime directive, do not muck up the timeline by killing your own grandparent in the past.',
            'person_id' => 4594,
            'for_rangers' => true,
            'for_pnvs' => true,
            'for_auditors' => true,
            'expires_at' => '2099-09-01 12:00:00'
        ]);

        $trueSettings = [
            'RadioCheckoutAgreementEnabled',
            'MotorPoolProtocolEnabled',
            'EventManagementOnPlayaEnabled',
            'RadioInfoAvailable',
            'TimesheetCorrectionEnable',
            'BroadcastClubhouseSandbox',
            'BroadcastMailSandbox',
            'BroadcastSMSSandbox',
            'HQWindowInterfaceEnabled'
        ];

        foreach ($trueSettings as $name) {
            $setting = Setting::find($name);
            $setting->value = 'true';
            $setting->saveWithoutValidation();
        }

        Setting::find('DashboardPeriod')->update(['value' => 'event']);

        self::passHQTraining("hqworkertest@nomail.none", $year);
        self::passHQTraining("hqshorttest@nomail.none", $year);
        self::passHQTraining("hqleadtest@nomail.none", $year);

        $hqPassword = env('RANGER_CLUBHOUSE_HQ_TRAINING_PASSWORD');
        if (!empty($hqPassword)) {
            self::setPassword("hqworkertest@nomail.none", $hqPassword);
            self::setPassword("hqshorttest@nomail.none", $hqPassword);
            self::setPassword("hqleadtest@nomail.none", $hqPassword);
        }
    }

    /**
     * Build the training database dump name.
     *
     * @param Carbon|string $dt
     * @return string
     */

    public static function trainingDatabaseDumpName(Carbon|string $dt): string
    {
        $date = Carbon::parse($dt)->format('Y-m-d');
        return "training-{$date}.sql.gz";
    }

    /**
     * Setup to an account has passed HQ Training
     */

    public static function passHQTraining(string $email, int $year): void
    {
        $person = Person::findByEmail($email);

        if (!$person) {
            Log::error("Account {$email} not found");
            return;
        }

        $slot = Slot::where('position_id', Position::HQ_FULL_TRAINING)->where('active', true)->where('begins_year', $year)->first();
        if (!$slot) {
            Log::error("Cannot find HQ training slot");
            return;
        }

        PersonSlot::insert(['slot_id' => $slot->id, 'person_id' => $person->id]);
        $ts = TraineeStatus::firstOrNewForSession($person->id, $slot->id);
        $ts->passed = true;
        $ts->saveWithoutValidation();
    }

    /**
     * Setup training data for the Mentors.
     *
     * - Convert the first Alpha shift to the GHD time, and delete all other shifts.
     * - Convert any Alpha account in the GHD year back to Alpha status regardless of current status.
     * - Sign back in any Mentor or MITTen that was on duty during the first day of Alpha shifts.
     * - Delete all Mentor/Alpha pods for the GHD year.
     * - Delete all Mentor assignments for the GHD year.
     * - Delete all Alpha scheduled signups that are not the Alpha shift, or In-Person trainings.
     *
     * @param string $ghd
     */

    public static function setupMentorTrainingData(string $ghd): void
    {
        $target = new Carbon($ghd);
        $year = $target->year;

        $personStatus = DB::table('person_status')
            ->where('new_status', Person::ALPHA)
            ->whereYear('created_at', $year)
            ->get();

        if ($personStatus->isEmpty()) {
            throw new RuntimeException("No Alphas found for {$year}");
        }

        $peopleIds = $personStatus->pluck('person_id')->toArray();

        DB::table('person')
            ->whereIntegerInRaw('id', $peopleIds)
            ->update(['status' => Person::ALPHA]);

        DB::table('person_position')->insertOrIgnore(
            array_map(fn($id) => [
                'person_id' => $id,
                'position_id' => Position::ALPHA
            ], $peopleIds)
        );

        DB::table('person_slot')
            ->join('slot', 'slot.id', 'person_slot.slot_id')
            ->where('slot.begins_year', $year)
            ->whereIntegerInRaw('person_slot.person_id', $peopleIds)
            ->whereNotIn('position_id', [Position::ALPHA, Position::TRAINING])
            ->delete();

        DB::table('person_mentor')
            ->where('mentor_year', $year)
            ->delete();

        $slots = Slot::where('begins_year', $year)
            ->where('position_id', Position::ALPHA)
            ->orderBy('begins')
            ->get();

        $pods = DB::table('pod')
            ->whereIn('slot_id', $slots->pluck('id'))
            ->get();

        if ($pods->isNotEmpty()) {
            DB::table('person_pod')
                ->whereIn('pod_id', $pods->pluck('id'))
                ->delete();
        }

        $saturday = null;
        foreach ($slots as $slot) {
            if ($slot->begins->dayOfWeek == 6 && !$saturday) {
                $saturday = $slot->begins->toDateString();
                $begins = $slot->begins;
                $begins->month = $target->month;
                $begins->day = $target->day;
                $begins->hour = $target->hour;
                $begins->minute = $target->minute;
                $slot->begins = $begins;
                $slot->ends = $begins->clone()->addHours(10);
                $slot->saveWithoutValidation();
            } else {
                $slot->delete();
            }
        }

        Timesheet::whereRaw('DATE(on_duty) != ?', [$saturday])
            ->whereYear('on_duty', $year)
            ->where('position_id', Position::ALPHA)
            ->delete();

        Timesheet::where('person_id', $peopleIds)
            ->whereNull('off_duty')
            ->delete();

        $rows = Timesheet::whereDate('on_duty', $saturday)
            ->where('position_id', Position::ALPHA)
            ->get()
            ->groupBy('person_id');

        foreach ($rows as $personId => $entries) {
            $ts = $entries->first();
            $onDuty = $ts->on_duty;
            $onDuty->month = $target->month;
            $onDuty->day = $target->day;
            $onDuty->hour = $target->hour;
            $onDuty->minute = $target->minute;
            $ts->on_duty = $onDuty;
            $ts->off_duty = null;
            $ts->saveWithoutValidation();
        }

        $mentors = Timesheet::where('position_id', [Position::MENTOR, Position::MENTOR_MITTEN])
            ->whereDate('on_duty', $saturday)
            ->orderBy('on_duty')
            ->get()
            ->groupBy('person_id');

        Timesheet::where('position_id', [Position::MENTOR, Position::MENTOR_MITTEN])
            ->whereYear('on_duty', $year)
            ->whereRaw('DATE(on_duty) != ?', [$saturday])
            ->delete();

        foreach ($mentors as $personId => $entries) {
            $mentor = $entries->first();
            // Kill any entry still on duty
            DB::table('timesheet')
                ->where('person_id', $mentor->person_id)
                ->where('id', '!=', $mentor->id)
                ->whereNull('off_duty')
                ->delete();
            $onDuty = $mentor->on_duty;
            $onDuty->month = $target->month;
            $onDuty->day = $target->day;
            $onDuty->hour = $target->hour;
            $onDuty->minute = $target->minute;
            $mentor->on_duty = $onDuty;
            $mentor->off_duty = null;
            $mentor->saveWithoutValidation();
        }
    }

    /**
     * Set the password on an account.
     *
     * @param string $email
     * @param string $password
     * @return void
     */

    public static function setPassword(string $email, string $password): void
    {
        $person = Person::findByEmail($email);
        if (!$person) {
            return;
        }
        $person->changePassword($password);
    }
}
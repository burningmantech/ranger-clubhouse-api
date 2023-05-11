<?php

namespace App\Lib;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GroundHogDay
{
    const ENVIRONMENT_VAR = 'RANGER_CLUBHOUSE_GROUNDHOG_DAY_TIME';

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
        DB::table('timesheet')->where('off_duty', '>', $groundHogDay)->update(['off_duty' => null]);

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
        DB::table('asset_person')->where('checked_in', '>=', $groundHogDay)->update(['checked_in' => null]);

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
            'MotorpoolPolicyEnable',
            'LoginManageOnPlayaEnabled',
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
            $setting->update();
        }

        Setting::find('DashboardPeriod')->update(['value' => 'event']);
    }

    /**
     * Build the training database dump name.
     *
     * @param Carbon|string $dt
     * @return string
     */
    public static function trainingDatabaseDumpName(Carbon|string $dt) : string
    {
        $date = Carbon::parse($dt)->format('Y-m-d');
        return "training-$date.sql.gz";
    }
}
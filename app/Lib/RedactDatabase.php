<?php

namespace App\Lib;

use App\Models\AccessDocument;
use App\Models\Person;
use App\Models\Provision;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Class RedactDatabase
 *
 * Used by the redaction and ground hog database commands to redact the current database and
 * set default settings (no credentials, sandbox mode, etc.)
 *
 * @package App\Lib
 */
class RedactDatabase
{
    public static function execute($year)
    {
        // No fruit-cup.. err.. Personal Information for you tonight!
        DB::table('person')->update([
            'on_site' => false,
            'home_phone' => '123-456-7890',
            'alt_phone' => '123-456-7890',
            'sms_on_playa' => '',
            'sms_off_playa' => '',
            'street1' => '123 Any St.',
            'street2' => '',
            'behavioral_agreement' => false,
            'message' => '',
            'bpguid' => 'DEAD-BEEF',
            'sfuid' => '',
            'camp_location' => 'D-Lot',
            'emergency_contact' => 'On-playa: John Smith (father), camped at 3:45 and G. Off-playa: Jane Smith (mother), phone 123-456-7890, email jane@noemail.none',
            'tpassword' => '',
            //  'password' => "$salt:$sha",
        ]);

        DB::table('person')->where('status', Person::ACTIVE)
            ->update([
                'email' => DB::raw("concat(substring(callsign_normalized, 1, 38), '@nomail.none')"),
            ]);

        DB::table('person')->where('status', '!=', Person::ACTIVE)
            ->update([
                'email' => DB::raw("concat(substring(callsign_normalized, 1, 34), '-', id, '@nomail.none')"),
            ]);

        // Zap training notes
        DB::table('trainee_status')->update(['notes' => '', 'rank' => null]);

        // And nuke a bunch of tables
        $tables = [
//            'access_document',
            'access_document_changes',
            'broadcast',
            'broadcast_message',
            'contact_log',
            'failed_jobs',
            'jobs',
            'log',
            'mail_log',
            'mentee_status',
            'motd',
            'oauth_client',
            'oauth_code',
            'person_event',
            'person_intake',
            'person_intake_note',
            'personal_access_tokens',
            'survey_answer',
            'trainee_note',
            'vehicle',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("TRUNCATE $table");
            }
        }

        DB::table('provision')->update(['status' => Provision::AVAILABLE]);

        DB::table('action_logs')->whereNotIn('event', ['person-slot-add', 'person-slot-remove'])->delete();

        DB::table('access_document')->update(['comments' => '']);
        DB::table('access_document')->whereYear('expiry_date', '>', current_year() + 3);

        $address = [
            'street1' => '1 Main St',
            'street2' => '',
            'city' => 'Springfield',
            'state' => 'NT',
            'postal_code' => '99999',
        ];

        foreach ($address as $key => $value) {
            DB::table('access_document')
                ->where($key, '!=', '')
                ->update([$key => $value]);
        }

        DB::table('access_document')
            ->where('type', AccessDocument::WAPSO)
            ->update(['name' => DB::raw('CONCAT("WAP Name #", id)')]);

        DB::table('access_document')
            ->where('type', '!=', AccessDocument::WAPSO)
            ->update(['name' => '']);

        // Zap all the Clubhouse message archives including the current table
        $rows = DB::select('SHOW TABLES LIKE "person_message%"');
        foreach ($rows as $row) {
            foreach ($row as $col => $name) {
                DB::statement("TRUNCATE $name");
            }
        }

        // Zap all the credentials
        $credentials = [];
        foreach (Setting::DESCRIPTIONS as $name => $setting) {
            if ($setting['is_credential'] ?? false) {
                $credentials[] = $name;
            }
        }
        DB::table('setting')->whereIn('name', $credentials)->update(['value' => '']);

        $settings = [
            'AllowSignupsWithoutPhoto' => 'true',
            'AuditorRegistrationDisabled' => 'true',
            'BroadcastClubhouseNotify' => 'false',
            'BroadcastClubhouseSandbox' => 'true',
            'HQWindowInterfaceEnabled' => 'true',
            'MealInfoAvailable' => 'true',
            'OnlineCourseDisabledAllowSignups' => 'true',
            'OnlineCourseEnabled' => 'false',
            'RadioInfoAvailable' => 'true',
            'TicketingPeriod' => 'offseason',
            'TimesheetCorrectionEnable' => 'true',
        ];

        foreach ($settings as $name => $value) {
            $setting = Setting::where('name', $name)->first();
            if (!$setting) {
                $setting = new Setting;
                $setting->name = $name;
            }

            $setting->value = $value;
            $setting->saveWithoutValidation();
        }
    }
}
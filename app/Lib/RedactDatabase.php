<?php

namespace App\Lib;

use App\Models\AccessDocument;
use App\Models\Person;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

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
            'access_document_changes',
            'access_document_delivery',
//            'access_document',
            'broadcast_message',
            'broadcast',
            'contact_log',
            'log',
            'mail_log',
            'motd',
            'mentee_status',
            'person_intake',
            'person_intake_note',
            'person_event',
            'vehicle',
            'survey_answer',
        ];

        foreach ($tables as $table) {
            DB::statement("TRUNCATE $table");
        }

        DB::table('access_document')
            ->whereNotIn('type', AccessDocument::PROVISION_TYPES)
            ->delete();

        DB::table('access_document')->update([ 'status' => AccessDocument::QUALIFIED]);

        DB::table('action_logs')->whereNotIn('event', ['person-slot-add', 'person-slot-remove'])->delete();

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
            'OnlineTrainingDisabledAllowSignups' => 'true',
            'OnlineTrainingEnabled' => 'false',
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
<?php

namespace App\Lib;

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

class RedactDatabase {
    public static function execute($year) {

       // $salt = '123456';
       // $sha = sha1($salt . 'donothing');

        // No fruit-cup.. err.. Personal Information for you tonight!
        DB::table('person')->update([
            'on_site'    => false,
            'home_phone' => '123-456-7890',
            'alt_phone' => '123-456-7890',
            'sms_on_playa' => '',
            'sms_off_playa' => '',
            'street1' => '123 Any St.',
            'street2' => '',
            'email' => DB::raw("concat(replace(callsign, ' ', ''), '@nomail.none')"),
            'behavioral_agreement' => false,
            'message'   => '',
            'bpguid' => 'DEAD-BEEF',
            'sfuid' => '',
            'camp_location' => 'D-Lot',
            'emergency_contact' => 'On-playa: John Smith (father), camped at 3:45 and G. Off-playa: Jane Smith (mother), phone 123-456-7890, email jane@noemail.none',
            'tpassword' => '',
          //  'password' => "$salt:$sha",
        ]);

        // Zap training notes
        DB::table('trainee_status')->update([ 'notes' => '', 'rank' => null ]);

        // And nuke a bunch of tables
        $tables = [
            'access_document_changes',
            'access_document_delivery',
            'access_document',
            'broadcast_message',
            'broadcast',
            'contact_log',
            'log',
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

        DB::delete("DELETE FROM action_logs WHERE event not in ('person-slot-add', 'person-slot-remove')");
        // Zap all the Clubhouse message archives including the current table
        $rows = DB::select('SHOW TABLES LIKE "person_message%"');
        foreach($rows as $row)
        {
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
        DB::table('setting')->whereIn('name', $credentials)->update([ 'value' => '' ]);

        $settings = [
            'BroadcastClubhouseNotify'         => 'false',
            'BroadcastClubhouseSandbox'        => 'true',
            'OnlineTrainingEnabled'           => 'false',
            'OnlineTrainingDisabledAllowSignups' => 'true',
            'AllowSignupsWithoutPhoto'          => 'true',
            'MealInfoAvailable'                => 'true',
            'RadioInfoAvailable'               => 'true',
            'TicketingPeriod'                  => 'offseason',
            'TimesheetCorrectionEnable'        => 'true',
            'HQWindowInterface'
        ];

        foreach ($settings as $name => $value) {
            $setting = Setting::where('name', $name)->first();
            if (!$setting) {
                $setting = new Setting;
                $setting->name = $name;
            }

            $setting->value =  $value;
            $setting->saveWithoutValidation();
        }
    }
}
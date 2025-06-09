<?php

namespace App\Lib;

use App\Models\AccessDocument;
use App\Models\Person;
use App\Models\Provision;
use App\Models\Setting;
use Carbon\Carbon;
use DateTime;
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
    public static function execute(int $year, bool $superRedact = false): void
    {
        // No fruit-cup.. err.. Personal Information for you tonight!
        DB::table('person')->update([
            'alt_phone' => '123-456-7890',
            'behavioral_agreement' => false,
            'bpguid' => 'DEAD-BEEF',
            'camp_location' => 'D-Lot',
            'emergency_contact' => 'On-playa: John Smith (father), camped at 3:45 and G. Off-playa: Jane Smith (mother), phone 123-456-7890, email jane@noemail.none',
            'employee_id' => '',
            'home_phone' => '123-456-7890',
            'known_pnvs' => '',
            'known_rangers' => '',
            'has_note_on_file' => false,
            'lms_id' => '',
            'lms_username' => '',
            'on_site' => false,
            'sfuid' => '',
            'sms_off_playa' => '',
            'sms_on_playa' => '',
            'street1' => '123 Any St.',
            'street2' => '',
            'tpassword' => '',
            'vehicle_blacklisted' => false
        ]);

        if ($superRedact) {
            DB::table('person')->update(['password' => password_hash('abcdef', Person::PASSWORD_ENCRYPTION)]);
        }

        DB::table('person')->where('status', Person::ACTIVE)
            ->update([
                'email' => DB::raw("concat(substring(callsign_normalized, 1, 38), '@nomail.none')"),
            ]);

        DB::table('person')->where('status', '!=', Person::ACTIVE)
            ->update([
                'email' => DB::raw("concat(substring(callsign_normalized, 1, 34), '-', id, '@nomail.none')"),
            ]);

        self::setupWESLTraining();

        // Zap training notes
        DB::table('trainee_status')->update(['notes' => '', 'rank' => null]);

        DB::table('action_logs')->whereNotIn('event', ['person-slot-add', 'person-slot-remove'])->delete();

        // And nuke a bunch of tables
        $tables = [
            'access_document_changes',
            'bmid_export',
            'broadcast',
            'broadcast_message',
            'cache',
            'cache_locks',
            'contact_log',
            'email_history',
            'error_logs',
            'failed_jobs',
            'jobs',
            'log',
            'mail_log',
            'manual_review',
            'mentee_status',
            'motd',
            'oauth_client',
            'oauth_code',
            'online_course',
            'person_banner',
            'person_event',
            'person_intake',
            'person_intake_note',
            'person_motd',
            'person_pog',
            'personal_access_tokens',
            'prospective_application',
            'prospective_application_log',
            'prospective_application_note',
            'request_log',
            'survey',
            'survey_answer',
            'survey_question',
            'telescope_entries',
            'telescope_entries_tags',
            'telescope_monitoring',
            'timesheet_log',
            'timesheet_missing',
            'timesheet_missing_note',
            'trainee_note',
            'vehicle',
        ];

        if ($superRedact) {
            $tables[] = 'person_photo';
        }

        DB::statement("SET FOREIGN_KEY_CHECKS = 0");
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("TRUNCATE $table");
            }
        }
        DB::statement("SET FOREIGN_KEY_CHECKS = 1");

        if ($superRedact) {
            DB::statement('TRUNCATE access_document');
            DB::statement('TRUNCATE bmid');
            DB::statement('TRUNCATE provision');
        } else {
            DB::table('provision')->update(['status' => Provision::AVAILABLE]);

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
        }

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
            'DatabaseCreatedOn' => (string)new Carbon(new DateTime),
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

    /**
     * Setup WESL training data
     *
     * @return void
     */

    public static function setupWESLTraining(): void
    {
        self::updatePerson(
            'Safety Phil',
            ['camp_location' => 'DPW Ghetto']
        );

        self::updatePerson(
            'keeper',
            ['emergency_contact' => "Morticia Addams (Mother), Gomez Addams (Father)\nPhone: +1-415-865-3800"]
        );
    }

    public static function updatePerson(string $callsign, array $data): void
    {
        DB::table('person')
            ->where('callsign_normalized', Person::normalizeCallsign($callsign))
            ->update($data);
    }
}

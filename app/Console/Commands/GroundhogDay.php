<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use App\Models\Person;

class GroundhogDay extends Command
{
    const GROUNDHOG_DATETIME = "2018-08-30 18:00:00";

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:groundhogday';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert database into a groundhog day database';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $year = date('Y', strtotime(self::GROUNDHOG_DATETIME));

        // Kill anytime sheets in the future
        DB::table('timesheet')->where('on_duty', '>', self::GROUNDHOG_DATETIME)->delete();
        // Mark timesheets ending after groundhog day as still on duty
        DB::table('timesheet')->where('off_duty', '>', self::GROUNDHOG_DATETIME)->update([ 'off_duty' => null ]);

        // Remove any timesheet logs after groundhog day
        DB::table('timesheet_log')->where('created_at', '>=', self::GROUNDHOG_DATETIME)->delete();
        DB::table('timesheet_missing')->where('created_at', '>=', self::GROUNDHOG_DATETIME)->delete();

        // Clear out all slots in future years
        $slotIds = DB::table('slot')->select('id')->whereYear('begins', '>', $year)->get()->pluck('id')->toArray();
        if (!empty($slotIds)) {
            // kill future year signups
            DB::table('person_slot')->whereIn('slot_id', $slotIds)->delete();
        }
        DB::table('slot')->whereYear('begins', '>', $year)->delete();

        DB::table('position_credit')->whereYear('start_time', '>', $year);

        // Remove all future training info
        DB::table('trainee_status')->whereIn('slot_id', $slotIds)->delete();

        // And redact the database
        $tables = [
            'action_logs',
            'access_document_changes',
            'access_document_delivery',
            'access_document',
            'broadcast_message',
            'broadcast',
            'contact_log',
            'feedback',
            'log',
            'mentee_status',
            'sessions',
            'ticket'
        ];

        foreach ($tables as $table) {
            DB::statement("TRUNCATE $table");
        }

        // Zap all the Clubhouse messages
        $rows = DB::select('SHOW TABLES LIKE "person_message%"');
        foreach($rows as $row)
        {
            foreach ($row as $col => $name) {
              DB::statement("TRUNCATE $name");
          }
        }

        // No fruit-cup.. err.. Personal Information for you tonight!
        DB::table('person')->update([
            'birthdate' => '1969-12-31',
            'home_phone' => '123-456-7890',
            'alt_phone' => '123-456-7890',
            'street1' => '123 Any St.',
            'street2' => '',
            'email' => DB::raw("concat(replace(callsign, ' ', ''), '@nomail.none')"),
            'em_home_phone' => '',
            'em_alt_phone' => '',
            'em_email' => '',
            'mentors_flag_note' => '',
            'mentors_notes' => '',
            'emergency_contact' => 'On-playa: John Smith (father), camped at 3:45 and G. Off-playa: Jane Smith (mother), phone 123-456-7890, email jane@noemail.none',
        ]);

        DB::table('setting')->where('is_encrypted', true)->update([ 'value' => '' ]);

        DB::table('trainee_status')->update([ 'notes' => '']);

    }
}

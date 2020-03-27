<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Motd;

use App\Lib\RedactDatabase;

class ClubhouseGroundHogDayCommand extends Command
{
    const GROUNDHOG_DATETIME = "2018-08-30 18:00:00";
    const GROUNDHOG_DATABASE = "rangers_ghd";

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = '';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clone current database, convert into a groundhog day database, and dump into file.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {

        $this->signature = 'clubhouse:groundhog-day
                    {-d|--dumpfile= : filename to dump the groundhog day database into. Default is rangers-groundhog-day-YYYY-MM-DD.sql}
                    {--tempdb=ranger_ghd : temporary database name}
                    {--day='.(date('Y') - 1).'-08-30 18:00:00 : ground hog day date/time}
                    ';
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $ghdname = $this->option('tempdb') ?? self::GROUNDHOG_DATABASE;
        $groundHogDay = $this->option('day') ?? self::GROUNDHOG_DATETIME;
        $ghdTime = strtotime($groundHogDay);
        $year = date('Y', $ghdTime);

        $user = config('database.connections.mysql.username');
        $pwd = config('database.connections.mysql.password');
        $db = config('database.connections.mysql.database');
        putenv("MYSQL_PWD=$pwd");

        $this->info("Creating groundhog day database from $db for day $groundHogDay");

        // Create the groundhog day database
        DB::statement("DROP DATABASE IF EXISTS $ghdname");
        DB::statement("CREATE DATABASE $ghdname");

        $this->info("Cloning $db to $ghdname");
        if (shell_exec("mysqldump -u $user $db | mysql -u $user $ghdname")) {
            $this->fatal("Cannot clone database");
        }

        // Switch databases
        config([ 'database.connections.mysql.database' => $ghdname ]);
        DB::purge('mysql');

        RedactDatabase::execute($year);

        // Kill anytime sheets in the future
        DB::table('timesheet')->where('on_duty', '>', $groundHogDay)->delete();
        // Mark timesheets ending after groundhog day as still on duty
        DB::table('timesheet')->where('off_duty', '>', $groundHogDay)->update([ 'off_duty' => null ]);

        // Remove any timesheet logs after groundhog day
        DB::table('timesheet_log')->where('created_at', '>=', $groundHogDay)->delete();
        DB::table('timesheet_missing')->where('created_at', '>=', $groundHogDay)->delete();

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


        // Kill all assets
        DB::table('asset')->whereYear('created_at', '>', $year);

        // Mark some assets as being checked out
        DB::table('asset_person')->whereYear('checked_in', '>=', $groundHogDay)->delete();
        DB::table('asset_person')->where('checked_out', '>=', $groundHogDay)->update([ 'checked_in' => null ]);

        // Mark everyone on site who has a timesheet or is schedule to work as on site and signed paperwork
        DB::table('person')
            ->where(function ($q)  use($ghdTime, $year) {
                $start = date('Y-08-20', $ghdTime);
                $end = date('Y-09-04', $ghdTime);
                $q->whereRaw("EXISTS (SELECT 1 FROM slot INNER JOIN person_slot ON person_slot.slot_id=slot.id WHERE (slot.begins >= '$start' AND slot.ends <= '$end') AND person_slot.person_id=person.id LIMIT 1)");
                $q->orWhereRaw("EXISTS (SELECT 1 FROM timesheet WHERE YEAR(timesheet.on_duty)=$year AND timesheet.person_id=person.id LIMIT 1)");
            })->update([
                'on_site'              => true,
                'asset_authorized'     => true,
                'vehicle_paperwork'    => true,
                'behavioral_agreement' => true,
              ]);
        // Setup an announcement

        DB::table('motd')->insert([
            'message'    => "Welcome to the Training Server where the date is always ".date('l, F jS Y', strtotime($groundHogDay)).".",
            'person_id' => 4594,
            'for_rangers' => true,
            'for_pnvs' => true,
            'for_auditors' => true
        ]);

        $this->info("Creating mysql dump of groundhog database");
        $dump = $this->option('dumpfile') ?? "rangers-groundhog-day-".date('Y-m-d').".sql";

        if (shell_exec("mysqldump -u $user $ghdname > $dump")) {
            $this->info("Failed to dump database - $ghdname has not been deleted.");
        } else {
            DB::statement("DROP DATABASE IF EXISTS $ghdname");
            $this->info("** Done! Database has been successfully created and dumped to $dump");
        }
    }
}

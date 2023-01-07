<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClubhouseCleanupMailLogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:cleanup-maillog';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge mail log records older than 6 months';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $count = DB::table('mail_log')->where('created_at', '<', now()->subMonth(6))->delete();
        $this->info("{$count} mail log records purged");
        return Command::SUCCESS;
    }
}

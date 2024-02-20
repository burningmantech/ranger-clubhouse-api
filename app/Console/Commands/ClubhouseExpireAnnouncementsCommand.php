<?php

namespace App\Console\Commands;

use App\Models\Motd;
use Illuminate\Console\Command;

class ClubhouseExpireAnnouncementsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:expire-announcements';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire Clubhouse Announcements (aka MOTDs)';

    /**
     * Execute the console command.
     */

    public function handle(): void
    {
        $rows = Motd::where('expires_at', '<', now())->get();
        foreach ($rows as $row) {
            $row->delete();
        }
        $this->info("Expired {$rows->count()} announcement(s).");
    }
}

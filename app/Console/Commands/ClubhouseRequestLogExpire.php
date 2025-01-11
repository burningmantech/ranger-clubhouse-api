<?php

namespace App\Console\Commands;

use App\Models\RequestLog;
use Illuminate\Console\Command;

class ClubhouseRequestLogExpire extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:request-log-expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire request logs older than '.RequestLog::EXPIRE_DAYS_DEFAULT. ' days';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        RequestLog::expire();
        $this->info('Request log expired.');
    }
}

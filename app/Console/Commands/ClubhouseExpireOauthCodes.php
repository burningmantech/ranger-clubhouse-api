<?php

namespace App\Console\Commands;

use App\Models\OauthCode;
use Illuminate\Console\Command;

class ClubhouseExpireOauthCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:expire-oauth-codes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired oauth codes';

    /**
     * Execute the console command.
     */

    public function handle(): void
    {
        OauthCode::deleteExpired();
    }
}

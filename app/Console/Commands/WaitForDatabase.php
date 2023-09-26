<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WaitForDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:wait {--time=60: wait X seconds until database is ready}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Wait until the database becomes ready.';

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
     * @return void
     */
    public function handle(): void
    {
        $time = $this->option('time');
        for ($i = 0; $i < $time; $i++) {
            try {
                DB::connection()->getPdo();
                $this->info('Database is alive');
                exit(0);
            } catch (Exception $e) {
                sleep(1);
            }
        }

        $this->error('Database is offline or credentials are wrong.');
        exit(1);
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use App\Models\Person;
use App\Models\Setting;

class WaitForDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:wait
                    {-t|--time= : wait X seconds until database is ready}
                    ';

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
     * @return mixed
     */
    public function handle()
    {
        $time = $this->option('time') ?? 60;
        for ($i = 0; $i < $time; $i++) {
            try {
                DB::connection()->getPdo();
                $this->info('Database is alive');
                exit(0);
            } catch (\Exception $e) {
                sleep(1);
            }
        }

        $this->error('Database is offline or credentials are wrong.');
        exit(1);
        // Clean up
    }
}

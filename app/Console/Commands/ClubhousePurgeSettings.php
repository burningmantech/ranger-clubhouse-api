<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Setting;

class ClubhousePurgeSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:purge-settings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge any Clubhouse setting from the database that are no longer defined in app/Models/Settings.php';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $settings = Setting::all();

        foreach ($settings as $setting) {
            if (!isset(Setting::DESCRIPTIONS[$setting->name])) {
                $this->info("Purging {$setting->name}");
                $setting->delete();
            }
        }
        return 0;
    }
}

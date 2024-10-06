<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Vehicle;
use App\Mail\VehiclePendingMail;

class ClubhouseVehiclePendingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:vehicle-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send an email to people listed in VehiclePendingEmail if vehicle requests are queued for review';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $pendingVehicles = Vehicle::findAllPending();

        $email = setting('VehiclePendingEmail');
        if ($pendingVehicles->isNotEmpty() && !empty($email)) {
            mail_send(new VehiclePendingMail($pendingVehicles));
        }
    }
}

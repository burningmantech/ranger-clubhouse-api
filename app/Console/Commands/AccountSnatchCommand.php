<?php

namespace App\Console\Commands;

use App\Models\Person;
use Illuminate\Console\Command;

class AccountSnatchCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:account-snatch
        {--id= : Person id to grant all the roles to}
        {--callsign= : Callsign of account to grant all the roles to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset an account password to abcdef (developer command)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (config('clubhouse.DeploymentEnvironment') == 'Production') {
            $this->error('This command is not available in the production environment.');
            exit(-1);
        }

        $id = $this->option('id');
        $callsign = $this->option('callsign');

        if (!$id && !$callsign) {
            $this->error('Neither the --id nor --callsign option was given.');
            exit(-1);
        }

        if ($id) {
            $person = Person::find($id);
        } else {
            $person = Person::findByCallsign($callsign);
        }

        if (!$person) {
            if ($id) {
                $this->error("The person record id #{$id} was not found.");
            } else {
                $this->error("The callsign '{$callsign}' was not found.");
            }
            exit(-1);
        }

        if (!$this->confirm("Are you absolutely sure you want to reset the password for '{$person->callsign}' to abcdef?")) {
            $this->error('Aborted - password untouched');
            exit(-1);
        }

        $person->changePassword('abcdef');
        $this->info('Password was successfully set.');
        exit(0);
    }
}

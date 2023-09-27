<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Models\PersonRole;
use App\Models\Role;
use Illuminate\Console\Command;
use JetBrains\PhpStorm\NoReturn;

class GrantAllRolesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:grant-all-roles 
        {--id= : Person id to grant all the roles to}
        {--callsign= : Callsign of account to grant all the roles to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Grant all the roles to an account (developer command)';

    /**
     * Execute the console command.
     */

    #[NoReturn]
    public function handle(): void
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

        if (!$this->confirm("Are you absolutely sure you want to grant all the roles to '{$person->callsign}' (id #{$person->id})?")) {
            $this->error('Aborting - no roles were granted.');
            exit(0);
        }

        PersonRole::addIdsToPerson($person->id, Role::pluck('id')->toArray(), 'granted via grant all roles command');

        $this->info('All the roles have been granted.');
        exit(0);
    }
}

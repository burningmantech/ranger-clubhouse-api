<?php

/*
 * taken from
 * https://medium.com/@smayzes/lets-build-an-api-in-15-minutes-867e59820d91
 *
 * This extends the "php artist make:model" command to place models in app/Models
 */

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ModelMakeCommand as Command;

class ModelMakeCommand extends Command
{
    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return "{$rootNamespace}\Models";
    }
}

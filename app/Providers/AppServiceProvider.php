<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use DB;
use Event;
use Log;
use Illuminate\Support\Facades\App;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (app()->isLocal()) {
            Event::listen('Illuminate\Database\Events\QueryExecuted', function ($query) {
                $placeholder = preg_quote('?', '/');
                $sql = $query->sql;
                foreach ($query->bindings as $binding) {
                    if (is_bool($binding)) {
                        $binding = $binding ? "TRUE" : "FALSE";
                    } else if ($binding === NULL) {
                        $binding = "NULL";
                    } else {
                        $binding = is_numeric($binding) ? $binding : "'{$binding}'";
                    }
                    $sql = preg_replace('/' . $placeholder . '/', $binding, $sql, 1);
                }
                // replace all newlines with spaces except those in quotes
                $sql = preg_replace('/\n(?![^"]*"(?:(?:[^"]*"){2})*[^"]*$)/i', ' ', $sql);
                $sql = preg_replace('/\s{2,}/i', ' ', $sql);
                error_log("SQL [$query->time ms] $sql");
            });
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}

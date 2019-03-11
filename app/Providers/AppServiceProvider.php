<?php

namespace App\Providers;

use Blade;
use DB;
use Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // For MySQL older than 5.7.7 and MariaDB older than 10.2.2
        // See https://laravel.com/docs/master/migrations#indexes
        Schema::defaultStringLength(191);

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

        Blade::directive('hyperlinktext', function($text) {
            return '<?php echo \App\Helpers\HyperLinkHelper::text('.$text.'); ?>';
        });

        Validator::extendImplicit('state_for_country', '\App\Validators\StateForCountry@validate', 'A state/province is required');
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

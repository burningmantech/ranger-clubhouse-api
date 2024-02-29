<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Allow modern sized photos to be uploaded
        ini_set('upload_max_filesize', '32M');
        ini_set('post_max_size', '32M');

        if (env('APP_SQL_DEBUG')) {
            DB::listen(function ($query) {
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
                Log::debug("$query->time ms: SQL $sql");
            });
        }

        Blade::directive('hyperlinktext', function ($text) {
            return '<?php echo \App\Helpers\HyperLinkHelper::text(' . $text . '); ?>';
        });

        Validator::extendImplicit('state_for_country', '\App\Validators\StateForCountry@validate', 'A state/province is required');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }
}

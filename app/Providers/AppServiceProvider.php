<?php

namespace App\Providers;

use App\Http\Middleware\RequestLoggerMiddleware;
use App\Models\Bmid;
use App\Models\Document;
use App\Models\Help;
use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\Role;
use App\Models\Setting;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Builder;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
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

        Builder::macro('deleteWithReason', function(string $reason) {
            $rows = $this->get();
            foreach ($rows as $row) {
                $row->auditReason = $reason;
                $row->delete();
            }
            return $rows;
        });


        $this->bootAuth();
        $this->bootRoute();
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        // The logger needs to stay instantiated between handle() and terminate()
        $this->app->singleton(RequestLoggerMiddleware::class);

        if (config('telescope.enabled')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    public function bootAuth(): void
    {
        Gate::define('isAdmin', function (Person $user) {
            return $user->isAdmin();
        });

        Gate::define('isMentor', function (Person $user) {
            return $user->hasRole([Role::ADMIN, Role::MENTOR]);
        });

        Gate::define('isIntake', function (Person $user) {
            return $user->hasRole(Role::INTAKE);
        });

        Gate::define('isVC', function (Person $user) {
            return $user->hasRole(Role::VC);
        });

        Gate::define('isTimesheetManager', function (Person $user) {
            return $user->hasRole([Role::ADMIN, Role::TIMESHEET_MANAGEMENT]);
        });
        Gate::resource('person', 'PersonPolicy');
    }

    public function bootRoute(): void
    {
        Route::bind('bmid', function ($id) {
            return Bmid::findOrFail($id);
        });

        Route::bind('document', function ($id) {
            return Document::findIdOrTagOrFail($id);
        });

        Route::bind('help', function ($id) {
            return Help::findByIdOrSlugOrFail($id);
        });

        Route::bind('person-event', function ($id) {
            return PersonEvent::findForRoute($id);
        });

        Route::bind('setting', function ($id) {
            return Setting::findOrFail($id);
        });
    }
}

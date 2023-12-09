<?php

namespace App\Providers;

use App\Models\Help;
use App\Models\PersonEvent;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot(): void
    {
        parent::boot();

        Route::bind('help', function ($id) {
            return Help::findByIdOrSlug($id) ?? abort(404);
        });

        Route::bind('person-event', function ($id) {
            return PersonEvent::findForRoute($id) ?? abort(404);
        });

        $this->routes(function () {
            Route::prefix('')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));
        });
    }
}

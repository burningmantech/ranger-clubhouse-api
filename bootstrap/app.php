<?php

use App\Exceptions\Handler;
use App\Http\Middleware\AccountGuard;
use App\Http\Middleware\RequestLogger;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use PHPOpenSourceSaver\JWTAuth\Providers\LaravelServiceProvider;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Middleware\TrustProxies;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        LaravelServiceProvider::class,
    ])
    ->withRouting(
      //  web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        // channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware) {
        /*
               $middleware->redirectGuestsTo(fn() => route('login'));
               $middleware->redirectUsersTo('/home');
        */

        $middleware->append(RequestLogger::class);

        $middleware->api(AccountGuard::class);

        $middleware->use([
            TrustProxies::class,
            HandleCors::class,
            //  PreventRequestsDuringMaintenance::class,
            ValidatePostSize::class,
            TrimStrings::class,
        ]);
        /*
                $middleware->replace(\Illuminate\Foundation\Http\Middleware\TrimStrings::class, TrimStrings::class);
                $middleware->replace(\Illuminate\Http\Middleware\TrustProxies::class, TrustProxies::class);
        */
        $middleware->alias([
            'bindings' => SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport(Handler::NO_REPORTING);
        $exceptions->report(fn(Throwable $e) => Handler::report($e));
        $exceptions->render(fn(Throwable $e, Request $request) => Handler::render($e, $request));
    })->create();

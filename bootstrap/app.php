<?php

use App\Exceptions\Handler;
use App\Http\Middleware\AccountGuard;
use App\Http\Middleware\RequestLoggerMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
    //  web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        // channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->use([
            RequestLoggerMiddleware::class,
            TrustProxies::class,
            HandleCors::class,
            //  PreventRequestsDuringMaintenance::class,
            ValidatePostSize::class,
            TrimStrings::class,
            AccountGuard::class,
        ]);

        $middleware->alias([
            'bindings' => SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport(Handler::NO_REPORTING);
        $exceptions->report(fn(Throwable $e) => Handler::report($e));
        $exceptions->render(fn(Throwable $e, Request $request) => Handler::render($e, $request));
    })->create();

<?php

namespace App\Http\Middleware;

use Closure;

use Illuminate\Support\Facades\Log;

class RequestLogger
{
    const GREEN = "[32m";
    const RED = "[31m";

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        return $next($request);
    }

    public function terminate($request, $response)
    {
        if (!app()->isLocal()){
            return;
        }

        $endTime = microtime(true);
        $status = method_exists($response, 'status') ? $response->status() : 200;
        $color = $status >= 500 ? self::RED : self::GREEN;

        Log::debug("\033".$color.'['.number_format(($endTime - LARAVEL_START) * 100, 3).' ms] '.$status . ' '. $request->method().' '.$request->fullUrl()."\033[0m");

    }
}

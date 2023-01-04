<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Log;

class RequestLogger
{
    /**
     * Handle an incoming request.
     *
     * @param  $request
     * @param  $next
     * @return mixed
     */
    public function handle($request, $next): mixed
    {
        return $next($request);
    }

    /**
     * Show what request was handled.
     *
     * @param $request
     * @param $response
     * @return void
     */

    public function terminate($request, $response): void
    {
        if (!app()->isLocal()) {
            return;
        }

        $endTime = microtime(true);
        $status = method_exists($response, 'status') ? $response->status() : 200;
        $type = $status >= 500 ? 'error' : 'debug';

        Log::$type(number_format(($endTime - LARAVEL_START) * 100, 3) . ' ms: ' . $status . ' ' . $request->method() . ' ' . $request->fullUrl());
    }
}

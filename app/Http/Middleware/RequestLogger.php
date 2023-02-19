<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Log;

class RequestLogger
{
    protected float $startTime = 0;

    /**
     * Handle an incoming request.
     *
     * @param  $request
     * @param  $next
     * @return mixed
     */
    public function handle($request, $next): mixed
    {
        $this->startTime = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
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

        if (!$this->startTime) {
            $this->startTime = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
        }

        $status = method_exists($response, 'status') ? $response->status() : 200;
        $type = $status >= 500 ? 'error' : 'debug';

        Log::$type(number_format((microtime(true) - $this->startTime) * 100, 3) . ' ms: ' . $status . ' ' . $request->method() . ' ' . $request->fullUrl());
    }
}

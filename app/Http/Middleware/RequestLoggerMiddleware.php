<?php

namespace App\Http\Middleware;

use App\Models\RequestLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RequestLoggerMiddleware
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

        $status = method_exists($response, 'status') ? $response->status() : 200;
        $type = $status >= 500 ? 'error' : 'debug';

        $time = (microtime(true) - $this->startTime) * 100;
        $method = $request->method();

        Log::$type(number_format($time, 3) . ' ms: ' . $status . ' ' . $method . ' ' . $request->fullUrl());

        if (app()->isLocal()) {
            return;
        }

        if ($method == 'OPTIONS') {
            // Don't record CORS preflight requests
            return;
        }

        if (method_exists($response, 'file')) {
            $size = $response->file->getSize();
        } else {
            $size = method_exists($response, 'content') ? strlen($response->content()) : 0;
        }

        RequestLog::record(Auth::id(), request_ip(), $request->path(), $status, $method, $size, $time);
    }
}

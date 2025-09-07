<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatedCheckMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     * @throws AuthenticationException
     */

    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            throw new AuthenticationException();
        }
        return $next($request);
    }
}

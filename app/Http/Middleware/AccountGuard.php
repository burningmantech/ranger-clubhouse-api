<?php

namespace App\Http\Middleware;

use App\Models\Person;
use Carbon\Carbon;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AccountGuard
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     * @throws AuthorizationException
     */

    public function handle(Request $request, Closure $next): Response
    {
        /*
         * The JWT token has an expiry timestamp - do the authentication check
         * before setting up the groundhog day time, otherwise the JWT token will be invalidated
         */

        $ghdTime = config('clubhouse.GroundhogDayTime');
        if (!empty($ghdTime)) {
            Carbon::setTestNow();
            $check = $this->checkUser();
            // Remember the temporal prime directive - don't kill your own grandfather when traveling to the past
            Carbon::setTestNow($ghdTime);
        } else {
            $check = $this->checkUser();
        }

        if ($check) {
            $user = Auth::user();
            if (in_array($user->status, Person::LOCKED_STATUSES)) {
                // A user should not be able to log in when not authorized.
                // However, a user could be logged in when their account is disabled.
                throw new AuthorizationException('Account is disabled.');
            }

            $user->retrieveRoles();

            // Update the time the person was last seen. Avoid auditing and perform a faster-ish update
            // then doing $user->last_seen_at = now(); $user->save();
            DB::table('person')->where('id', $user->id)->update(['last_seen_at' => now()]);
        }

        return $next($request);
    }

    /**
     * See which guard handles the authentication, and set that as the default.
     *
     * @return bool
     */

    private function checkUser(): bool
    {
        foreach (['api', 'jwt'] as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::shouldUse($guard);
                return true;
            }
        }

        return false;
    }
}

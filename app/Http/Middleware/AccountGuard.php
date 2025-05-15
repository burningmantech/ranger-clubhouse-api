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
         * The authentication tokens have an expiry timestamp - do the authentication check
         * before setting up the groundhog day time, otherwise the token will be invalidated
         */

        $ghdTime = config('clubhouse.GroundhogDayTime');
        if (!empty($ghdTime)) {
            Carbon::setTestNow();
            $check = Auth::check();
            // Remember the temporal prime directive - don't kill your own grandfather when traveling to the past
            Carbon::setTestNow($ghdTime);
        } else {
            $check = Auth::check();
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
}

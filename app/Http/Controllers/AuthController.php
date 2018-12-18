<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Controller;

use App\Models\Person;
use App\Models\Role;
use App\Models\ActionLog;
use App\Http\RestApi;
use App\Mail\ResetPassword;
use DB;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        DB::select("SET time_zone = '-7:00'");
        $this->middleware('auth:api', ['except' => ['login', 'resetPassword']]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        $credentials = request()->validate([
            'identification' => 'required',
            'password'       => 'required',
        ]);

        $actionData = [
            'ip'         => request()->ip(),
            'user_agent' => request()->userAgent(),
            'email'      => $credentials['identification']
        ];

        $person = Person::findForAuthentication($credentials);

        if (!$person) {
            ActionLog::record(null, 'auth-failed', 'Credentials incorrect', $actionData);
            return response()->json([ 'error' => 'The email and/or password is incorrect.'], 401);
        }

        if ($person->user_authorized == false) {
            ActionLog::record($person, 'auth-failed', 'Account disabled', $actionData);
            return response()->json([ 'error' => 'The account has been disabled.'], 401);
        }

        if (!$person->hasRole(Role::LOGIN)) {
            ActionLog::record($person, 'auth-failed', 'Login disabled', $actionData);
            return response()->json([ 'error' => 'The account has been temporarily disabled from logging.'], 401);
        }

        ActionLog::record($person, 'auth-login', 'User login', $actionData);
        return $this->respondWithToken(auth()->login($person), $person);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        ActionLog::record($this->user, 'auth-logout', 'User logout');
        auth()->logout();

        return response()->json(['status' => 'success']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    public function resetPassword()
    {
        $data = request()->validate([
            'identification' => 'required|email'
        ]);

        $action = [
            'ip'            => request()->ip(),
            'user_agent'    => request()->userAgent(),
            'email'         => $data['identification']
        ];

        try {
            $person = Person::findEmailOrFail($data['identification']);
        } catch (\Exception $e) {
            ActionLog::record(null, 'auth-password-reset-fail', 'Password reset failed', $action);
            throw $e;
        }

        $resetPassword = $person->createResetPassword();

        ActionLog::record($person, 'auth-password-reset-success', 'Password reset request', $action);

        Mail::to($person->email)->send(new ResetPassword($resetPassword, config('clubhouse.GeneralSupportEmail')));

        return response()->json([ 'status' => 'success' ]);
    }
    /**
     * Get the JWT token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token, $person)
    {
        // TODO does a 'refresh_token' need to be provided?
        return response()->json([
            'token'      => $token,
            'person_id'  => $person->id,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }
}

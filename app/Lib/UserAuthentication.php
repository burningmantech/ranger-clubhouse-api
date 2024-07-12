<?php

namespace App\Lib;

use App\Models\ActionLog;
use App\Models\Person;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserAuthentication
{
    public static function attempt(string $email, string $password, bool $isJWT): JsonResponse
    {
        $actionData = self::buildLogInfo();

        $person = Person::where('email', $email)->first();
        if (!$person) {
            $actionData['email'] = $email;
            ActionLog::record(null, 'auth-failed', 'Email not found', $actionData);
            return self::errorResponse('invalid-credentials', $isJWT);
        }

        if (!$person->isValidPassword($password)) {
            ActionLog::record($person, 'auth-failed', 'Password incorrect', $actionData);
            return self::errorResponse('invalid-credentials', $isJWT);
        }

        return self::loginUser($person, $actionData, $isJWT);
    }

    public static function buildLogInfo(): array
    {
        $actionData = [
            'ip' => request_ip(),
            'user_agent' => request()->userAgent(),
        ];

        $buildTimestamp = request()->input('build_timestamp');

        // Analytics to help figure out how our users interact with the site.
        $screenSize = request()->input('screen_size');
        if (!empty($screenSize)) {
            $actionData['screen_size'] = $screenSize;
        }

        $width = request()->input('width');
        if (!empty($width)) {
            $actionData['screen_size'] = [
                'width' => $width,
                'height' => request()->input('height')
            ];
        }

        if (!empty($buildTimestamp)) {
            $actionData['build_timestamp'] = $buildTimestamp;
        }

        return $actionData;
    }

    public static function loginUser(Person $person, $actionData, $isJWT): JsonResponse
    {
        $status = $person->status;

        if ($status == Person::SUSPENDED) {
            ActionLog::record($person, 'auth-failed', 'Account suspended', $actionData);
            return self::errorResponse('account-suspended', $isJWT);
        }

        if (in_array($status, Person::LOCKED_STATUSES)) {
            ActionLog::record($person, 'auth-failed', 'Account disabled', $actionData);
            return self::errorResponse('account-disabled', $isJWT);
        }

        $person->logged_in_at = now();
        $person->saveWithoutValidation();

        ActionLog::record($person, 'auth-login', 'User login', $actionData);

        $token = self::groundHogDayWrap(fn() => ($isJWT ? Auth::guard('jwt')->login($person) : $person->createToken('login')->plainTextToken));

        return self::respondWithToken($token, $person, $isJWT);
    }

    /**
     * Deal with Ground Hog Day server timing
     *
     * @param $closure
     * @return mixed
     */

    private static function groundHogDayWrap($closure): mixed
    {
        $ghd = config('clubhouse.GroundhogDayTime');
        if (!empty($ghd)) {
            Carbon::setTestNow();
            $result = $closure();
            Carbon::setTestNow($ghd);
        } else {
            $result = $closure();
        }

        return $result;
    }

    /**
     * Get the JWT token array structure.
     *
     * @param string $token
     * @param Person $person
     * @param bool $isJWT
     * @return JsonResponse
     */

    public static function respondWithToken(string $token, Person $person, bool $isJWT): JsonResponse
    {
        if ($isJWT) {
            $payload = [
                'token' => $token,
                'token_type' => 'bearer',
            ];
        } else {
            $payload = [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => (config('clubhouse.DeploymentEnvironment') == 'Training' ? config('sanctum.training_server_expiration') : config('sanctum.expiration')) * 60,
                'person_id' => $person->id,
            ];
        }

        return response()->json($payload);
    }

    /**
     * Attempt a login via a temporary (password reset) token.
     *
     * @param string $token
     * @param bool $isJWT
     * @return JsonResponse
     */

    public static function attemptTemporaryTokenLogin(string $token, bool $isJWT): JsonResponse
    {
        $actionData = self::buildLogInfo();

        $person = Person::where('tpassword', $token)->first();
        if (!$person) {
            ActionLog::record(null, 'auth-failed', 'Temporary login token not found', $actionData);
            return self::errorResponse('invalid-token', $isJWT);
        }

        if ($person->tpassword_expire < now()->timestamp) {
            ActionLog::record($person, 'auth-failed', 'Temporary login token expired', $actionData);
            return self::errorResponse('token_expired', $isJWT);
        }

        return UserAuthentication::loginUser($person, $actionData, $isJWT);
    }

    public static function errorResponse(string $status, bool $isJWT): JsonResponse
    {
        return response()->json([($isJWT ? 'status' : 'error') => $status], 401);
    }
}
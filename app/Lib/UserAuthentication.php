<?php

namespace App\Lib;

use App\Models\ActionLog;
use App\Models\Person;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class UserAuthentication
{
    public static function attempt(string $email, string $password): JsonResponse
    {
        $actionData = self::buildLogInfo();

        $person = Person::where('email', $email)->first();
        if (!$person) {
            $actionData['email'] = $email;
            ActionLog::record(null, 'auth-failed', 'Email not found', $actionData);
            return self::errorResponse('invalid-credentials');
        }

        if (!$person->isValidPassword($password)) {
            ActionLog::record($person, 'auth-failed', 'Password incorrect', $actionData);
            return self::errorResponse('invalid-credentials');
        }

        $person->updatePasswordEncryption($password);

        return self::loginUser($person, $actionData);
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

    public static function loginUser(Person $person, $actionData): JsonResponse
    {
        $status = $person->status;

        if ($status == Person::SUSPENDED) {
            ActionLog::record($person, 'auth-failed', 'Account suspended', $actionData);
            return self::errorResponse('account-suspended');
        }

        if (in_array($status, Person::LOCKED_STATUSES)) {
            ActionLog::record($person, 'auth-failed', 'Account disabled', $actionData);
            return self::errorResponse('account-disabled');
        }

        $person->logged_in_at = now();
        $person->saveWithoutValidation();

        ActionLog::record($person, 'auth-login', 'User login', $actionData);

        $token = self::groundHogDayWrap(fn() => $person->createToken('login')->plainTextToken);

        return self::respondWithToken($token, $person);
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
     * Respond with a token, adjusting the Groundhog Day time if need be.
     *
     * @param string $token
     * @param Person $person
     * @return JsonResponse
     */

    public static function respondWithToken(string $token, Person $person): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => (config('clubhouse.DeploymentEnvironment') == 'Training' ? config('sanctum.training_server_expiration') : config('sanctum.expiration')) * 60,
            'person_id' => $person->id,
        ]);
    }

    /**
     * Attempt a login via a temporary (password reset) token.
     *
     * @param string $token
     * @return JsonResponse
     */

    public static function attemptTemporaryTokenLogin(string $token): JsonResponse
    {
        $actionData = self::buildLogInfo();

        $person = Person::where('tpassword', $token)->first();
        if (!$person) {
            ActionLog::record(null, 'auth-failed', 'Temporary login token not found', $actionData);
            return self::errorResponse('invalid-token');
        }

        if ($person->tpassword_expire < now()->timestamp) {
            ActionLog::record($person, 'auth-failed', 'Temporary login token expired', $actionData);
            return self::errorResponse('token_expired');
        }

        return UserAuthentication::loginUser($person, $actionData);
    }

    public static function errorResponse(string $status): JsonResponse
    {
        return response()->json(['error' => $status], 401);
    }
}
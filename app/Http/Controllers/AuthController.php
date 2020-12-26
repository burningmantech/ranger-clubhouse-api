<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Controller;

use App\Models\ActionLog;
use App\Models\ErrorLog;
use App\Models\Person;
use App\Models\Role;

use App\Mail\ResetPassword;

use Carbon\Carbon;

use GuzzleHttp;
use GuzzleHttp\Exception\RequestException;
use Okta\JwtVerifier\JwtVerifierBuilder;

class AuthController extends Controller
{
    /**
     * Get a JWT via given credentials.
     *
     * @return JsonResponse
     */

    public function login()
    {
        $code = request()->input('sso_code');

        if (!empty($code)) {
            return $this->handleSSOLogin($code);
        }

        $actionData = $this->buildLogInfo();

        $token = request()->input('temp_token');
        if (!empty($token)) {
            $person = Person::where('tpassword', $token)->first();
            if (!$person) {
                ActionLog::record(null, 'auth-failed', 'Temporary login token not found', $actionData);
                return response()->json(['status' => 'invalid-token'], 401);
            }

            if ($person->tpassword_expire < now()->timestamp) {
                ActionLog::record(null, 'auth-failed', 'Temporary login token expired', $actionData);
                return response()->json(['status' => 'invalid-token'], 401);
            }
        } else {
            $credentials = request()->validate([
                'identification' => 'required|string',
                'password' => 'required|string',
            ]);
            $person = Person::where('email', $credentials['identification'])->first();
            if (!$person) {
                $actionData['email'] = $credentials['identification'];
                ActionLog::record(null, 'auth-failed', 'Email not found', $actionData);
                return response()->json(['status' => 'invalid-credentials'], 401);
            }

            if (!$person->isValidPassword($credentials['password'])) {
                ActionLog::record($person, 'auth-failed', 'Password incorrect', $actionData);
                return response()->json(['status' => 'invalid-credentials'], 401);
            }
        }

        return $this->attemptLogin($person, $actionData);
    }

    private function buildLogInfo()
    {
        $actionData = [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        $screenSize = request()->input('screen_size');
        $buildTimestamp = request()->input('build_timestamp');

        // Analytics to help figure out how our users interact with the site.
        if (!empty($screenSize)) {
            $actionData['screen_size'] = $screenSize;
        }

        if (!empty($buildTimestamp)) {
            $actionData['build_timestamp'] = $buildTimestamp;
        }

        return $actionData;
    }

    /**
     * Attempt to login (aka responded with a token) the user
     *
     * Handles the common checks for both username/password and SSO logins.
     */

    private function attemptLogin(Person $person, $actionData)
    {
        $status = $person->status;

        if ($status == Person::SUSPENDED) {
            ActionLog::record($person, 'auth-failed', 'Account suspended', $actionData);
            return response()->json(['status' => 'account-suspended'], 401);
        }

        if (in_array($status, Person::LOCKED_STATUSES)) {
            ActionLog::record($person, 'auth-failed', 'Account disabled', $actionData);
            return response()->json(['status' => 'account-disabled'], 401);
        }

        if (!$person->hasRole(Role::LOGIN)) {
            ActionLog::record($person, 'auth-failed', 'Login disabled', $actionData);
            return response()->json(['status' => 'login-disabled'], 401);
        }

        $person->logged_in_at = now();
        $person->saveWithoutValidation();

        ActionLog::record($person, 'auth-login', 'User login', $actionData);

        $token = $this->groundHogDayWrap(function () use ($person) {
            return auth()->login($person);
        });
        return $this->respondWithToken($token, $person);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return JsonResponse
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
     * @return JsonResponse
     */
    public function refresh()
    {
        // TODO - test this
        return $this->respondWithToken(Auth::guard()->refresh());
    }

    /**
     * Reset an account password by emailing a new temporary password.
     */

    public function resetPassword()
    {
        $data = request()->validate([
            'identification' => 'required|email',
        ]);

        $action = [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'email' => $data['identification']
        ];

        $person = Person::findByEmail($data['identification']);

        if (!$person) {
            ActionLog::record(null, 'auth-password-reset-fail', 'Password reset failed', $action);
            return response()->json(['status' => 'not-found'], 400);
        }

        if (in_array($person->status, Person::LOCKED_STATUSES)) {
            ActionLog::record(null, 'auth-password-reset-fail', 'Account disabled', $action);
            return response()->json(['status' => 'account-disabled'], 403);
        }

        $token = $person->createTemporaryLoginToken();

        ActionLog::record($person, 'auth-password-reset-success', 'Password reset request', $action);

        if (!mail_to($person->email, new ResetPassword($person, $token, setting('AdminEmail')))) {
            return response()->json(['status' => 'mail-fail']);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     *
     * Handle a Okta (for now) SSO login.
     *
     * Query the SSO server to obtain the "claims" values and figure out who
     * the person is trying to login.
     */

    private function handleSSOLogin($code)
    {
        $clientId = config('okta.client_id');
        $issuer = config('okta.issuer');
        $authHeaderSecret = base64_encode($clientId . ':' . config('okta.client_secret'));

        $url = $issuer . '/v1/token';
        $client = new GuzzleHttp\Client();

        $actionData = $this->buildLogInfo();

        try {
            // Query the server - note the code is only valid for a few seconds.
            $res = $client->request('POST', $url, [
                'read_timeout' => 10,
                'connect_timeout' => 10,
                'query' => [
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => config('okta.redirect_uri'),
                    'code' => $code,
                ],
                'headers' => [
                    'Authorization' => "Basic $authHeaderSecret",
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
            ]);
        } catch (RequestException $e) {
            ErrorLog::recordException($e, 'auth-sso-connect-failure');
            return response()->json(['status' => 'sso-server-failure'], 401);
        }

        $body = $res->getBody()->getContents();

        if ($res->getStatusCode() != 200) {
            ErrorLog::record('sso-server-failure', ['status' => $res->getStatusCode(), 'body' => $res->getBody()]);
            return response()->json(['status' => 'sso-server-failure'], 401);
        }

        try {
            // Try to decode the token
            $json = json_decode($body);
            $jwtVerifier = (new JwtVerifierBuilder())
                ->setIssuer($issuer)
                ->setAudience('api://default')
                ->setClientId($clientId)
                ->build();

            $jwt = $jwtVerifier->verify($json->access_token);
            if (!$jwt) {
                ErrorLog::record('sso-malformed-token', ['body' => $body, 'jwt' => $jwt]);
                return response()->json(['status' => 'sso-token-failure'], 401);
            }

            $claims = $jwt->claims;
        } catch (Exception $e) {
            ErrorLog::recordException($e, 'sso-decode-failure', ['body' => $body]);
            return response()->json(['status' => 'sso-token-failure'], 401);
        }

        /*
         * TODO: if the plans go forward to support Okta SSO, the claims
         * values will need to include a BPGUID to correctly identify the
         * account. Email is not enough because the Clubhouse & SSO service
         * might be out of sync.
         */

        $email = $jwt->claims['sub'];
        $person = Person::where('email', $email)->first();
        if (!$person) {
            $actionData['email'] = $email;
            ActionLog::record(null, 'auth-sso-failed', 'Email not found', $actionData);
            return response()->json(['status' => 'invalid-credentials'], 401);
        }

        // Everything looks good so far.. perform some validation checks and
        // response with a token
        return $this->attemptLogin($person, $actionData);
    }

    /**
     * Get the JWT token array structure.
     *
     * @param string $token
     *
     * @return JsonResponse
     */

    protected function respondWithToken($token, $person)
    {
        return response()->json([
            'token' => $token,
            'token_type' => 'bearer',
            // TODO: remove in Jan or Feb 2021 (wait for browser caches to expire and everyone has the frontend
            // version which uses the 'sub' field in $token)
            'person_id' => $person->id,
        ]);
    }

    /**
     * Deal with Ground Hog Day server timing
     */

    private function groundHogDayWrap($closure)
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
}

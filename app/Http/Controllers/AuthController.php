<?php

namespace App\Http\Controllers;

use App\Lib\UserAuthentication;
use App\Mail\ResetPassword;
use App\Models\ActionLog;
use App\Models\Person;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    /**
     * Get a JWT via given credentials or temporary token.
     *
     * TODO: Remove when everyone's browser no longer has an old client cached.
     *
     * @return JsonResponse
     */

    public function jwtLogin(): JsonResponse
    {
        $token = request()->input('temp_token');

        if (!empty($token)) {
            return UserAuthentication::attemptTemporaryTokenLogin($token, true);
        }

        $credentials = request()->validate([
            'identification' => 'required|string',
            'password' => 'required|string',
        ]);

        return UserAuthentication::attempt($credentials['identification'], $credentials['password'], true);
    }

    /**
     * Log the user out (Invalidate the token). Common to both Oauth2 & JWT.
     *
     * @return JsonResponse
     */

    public function logout(): JsonResponse
    {
        ActionLog::record($this->user, 'auth-logout', 'User logout');
        auth()->logout();

        return response()->json(['status' => 'success']);
    }

    /**
     * Reset an account password by emailing a new temporary password.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function resetPassword(): JsonResponse
    {
        prevent_if_ghd_server('Reset password is not available on the training server.');

        $data = request()->validate([
            'identification' => 'required|email',
        ]);

        $action = [
            'ip' => request_ip(),
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

        if (!mail_to_person($person, new ResetPassword($person, $token), false)) {
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
#use Okta\JwtVerifier\JwtVerifierBuilder;
#    private function handleSSOLogin($code): JsonResponse
#    {
#        $clientId = config('okta.client_id');
#        $issuer = config('okta.issuer');
#        $authHeaderSecret = base64_encode($clientId . ':' . config('okta.client_secret'));
#
#        $url = $issuer . '/v1/token';
#        $client = new GuzzleHttp\Client();
#
#        $actionData = $this->buildLogInfo();
#
#        try {
#            // Query the server - note the code is only valid for a few seconds.
#            $res = $client->request('POST', $url, [
#                'read_timeout' => 10,
#                'connect_timeout' => 10,
#                'query' => [
#                    'grant_type' => 'authorization_code',
#                    'redirect_uri' => config('okta.redirect_uri'),
#                    'code' => $code,
#                ],
#                'headers' => [
#                    'Authorization' => "Basic $authHeaderSecret",
#                    'Accept' => 'application/json',
#                    'Content-Type' => 'application/x-www-form-urlencoded'
#                ],
#            ]);
#        } catch (RequestException $e) {
#            ErrorLog::recordException($e, 'auth-sso-connect-failure');
#            return response()->json(['status' => 'sso-server-failure'], 401);
#        }
#
#        $body = $res->getBody()->getContents();
#
#        if ($res->getStatusCode() != 200) {
#            ErrorLog::record('sso-server-failure', ['status' => $res->getStatusCode(), 'body' => $res->getBody()]);
#            return response()->json(['status' => 'sso-server-failure'], 401);
#        }
#
#        try {
#            // Try to decode the token
#            $json = json_decode($body);
#            $jwtVerifier = (new JwtVerifierBuilder())
#                ->setIssuer($issuer)
#                ->setAudience('api://default')
#                ->setClientId($clientId)
#                ->build();
#
#            $jwt = $jwtVerifier->verify($json->access_token);
#            if (!$jwt) {
#                ErrorLog::record('sso-malformed-token', ['body' => $body, 'jwt' => $jwt]);
#                return response()->json(['status' => 'sso-token-failure'], 401);
#            }
#
#            $claims = $jwt->claims;
#        } catch (Exception $e) {
#            ErrorLog::recordException($e, 'sso-decode-failure', ['body' => $body]);
#            return response()->json(['status' => 'sso-token-failure'], 401);
#        }
#
#        /*
#         * TODO: if the plans go forward to support Okta SSO, the claims
#         * values will need to include a BPGUID to correctly identify the
#         * account. Email is not enough because the Clubhouse & SSO service
#         * might be out of sync.
#         */
#
#        $email = $jwt->claims['sub'];
#        $person = Person::where('email', $email)->first();
#        if (!$person) {
#            $actionData['email'] = $email;
#            ActionLog::record(null, 'auth-sso-failed', 'Email not found', $actionData);
#            return response()->json(['status' => 'invalid-credentials'], 401);
#        }
#
#        // Everything looks good so far.. perform some validation checks and
#        // response with a token
#        return $this->loginUser($person, $actionData);
#    }
}

<?php

namespace App\Http\Controllers;

use App\Lib\UserAuthentication;
use App\Models\ActionLog;
use App\Models\ErrorLog;
use App\Models\OauthClient;
use App\Models\OauthCode;
use App\Models\Person;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use App\Exceptions\UnacceptableConditionException;
use Laravel\Sanctum\PersonalAccessToken;

class OAuth2Controller extends ApiController
{
    public function openIdDiscovery(): JsonResponse
    {
        if (app()->isLocal()) {
            // local development assumes we're also using a local moodle / ims instance.
            $authEndpoint = "http://127.0.0.1:4200/me/oauth2-grant";
            $tokenEndpoint = "http://docker.for.mac.localhost:8000/auth/oauth2/token";
            $userInfoEndpoint = "http://docker.for.mac.localhost:8000/auth/oauth2/userinfo";
        } else {
            $authEndpoint = "https://ranger-clubhouse.burningman.org/me/oauth2-grant";
            $tokenEndpoint = "https://ranger-clubhouse.burningman.org/api/auth/oauth2/token";
            $userInfoEndpoint = "https://ranger-clubhouse.burningman.org/api/auth/oauth2/userinfo";
        }

        return response()->json([
            "issuer" => "https://ranger-clubhouse.burningman.org",
            "authorization_endpoint" => $authEndpoint,
            "token_endpoint" => $tokenEndpoint,
            "userinfo_endpoint" => $userInfoEndpoint,
            "response_types_supported" => [
                "code",
                "token",
                "none"
            ],
            "subject_types_supported" => [
                "public"
            ],
            "id_token_signing_alg_values_supported" => [
                "RS256"
            ],
            "scopes_supported" => [
                "openid",
                "email",
                "profile"
            ],
            "token_endpoint_auth_methods_supported" => [
                "client_secret_post",
                "client_secret_basic"
            ],
            "claims_supported" => [
                "aud",
                "email",
                "exp",
                "family_name",
                "given_name",
                "iat",
                "iss",
                "sub"
            ],
            "code_challenge_methods_supported" => [
                "plain",
                "S256"
            ],
            "grant_types_supported" => [
                "authorization_code",
                "password",
            ]
        ]);
    }

    /**
     * Create a grant code and build the callback url for the frontend handling the login form.
     * Unlike the other methods in this controller, this one has to called by a logged-in user.
     *
     */

    public function grantOAuthCode(): JsonResponse
    {
        $params = request()->validate([
            'response_type' => 'required|string',
            'redirect_uri' => 'required|string',
            'client_id' => 'required|string|exists:oauth_client,client_id',
            'scope' => 'required|string',
            'state' => 'sometimes|string',
        ]);


        if ($params['response_type'] != 'code') {
            throw new UnacceptableConditionException('Unsupported response type');
        }

        $client = OauthClient::findForClientId($params['client_id']);
        $code = OauthCode::createCodeForClient($client, Auth::user(), $params['scope']);

        $callbackParams = ['code' => $code];
        if (!empty($params['state'])) {
            $callbackParams['state'] = $params['state'];
        }

        return response()->json([
            'callback_url' => $params['redirect_uri'] . '?' . http_build_query($callbackParams),
            'client_description' => $client->description,
        ]);
    }

    /**
     * Response to an OAuth2 token request
     *
     * @return JsonResponse
     */

    public function grantOAuthToken(): JsonResponse
    {
        $params = request()->validate([
            'grant_type' => [
                'required',
                'string',
                Rule::in(['authorization_code', 'password'])
            ]
        ]);

        if ($params['grant_type'] == 'authorization_code') {
            return $this->processAuthorizationCodeGrant();
        } else {
            return $this->processPasswordGrant();
        }
    }

    /**
     * Process an authorization code grant. The request should only come from registered
     * oauth2 clients such as the Moodle Server, or IMS.
     *
     * @return JsonResponse
     */

    public function processAuthorizationCodeGrant(): JsonResponse
    {
        $params = request()->validate([
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
            'code' => 'required|string',
        ]);

        $client = OauthClient::findForClientId($params['client_id']);
        if (!$client) {
            ErrorLog::record('oauth2-invalid-client-id', ['client_id' => $params['client_id']]);
            return response()->json(['error' => 'invalid_client'], 400);
        }

        if ($client->secret != $params['client_secret']) {
            ErrorLog::record('oauth2-invalid-client-secret', [
                'client_id' => $params['client_id'],
                'secret' => $params['client_secret']
            ]);
            return response()->json(['error' => 'invalid_client_secret'], 401);
        }

        $oc = OAuthCode::findForClientCode($client, $params['code']);
        if (!$oc) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        $person = Person::findOrFail($oc->person_id);
        $token = $person->createToken($client->client_id);

        ActionLog::record($person, 'oauth2-token-grant', 'token granted for ' . $client->client_id, [
            'id' => $client->id,
        ]);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_in' => config('sanctum.expiration') * 60,
        ]);
    }

    /**
     * Obtain an oauth2 token via login credentials.
     *
     * @return JsonResponse
     */

    public function processPasswordGrant(): JsonResponse
    {
        $params = request()->validate([
            'username' => 'required|string',
            'password' => 'required|string'
        ]);

        return UserAuthentication::attempt($params['username'], $params['password'], false);
    }

    /**
     * Return basic user information for oauth2 tokens
     *
     * @return JsonResponse
     */

    public function oauthUserInfo(): JsonResponse
    {
        $person = Auth::user();

        $status = $person->status;
        if (in_array($status, Person::ACTIVE_STATUSES)) {
            $first = "Ranger";
        } else {
            $first = ucfirst($status);
        }

        return response()->json([
            'chid' => $person->id,
            'username' => strtolower($person->callsign_normalized),
            'email' => $person->email,
            'given_name' => $first,
            'family_name' => $person->callsign,
        ]);
    }

    /**
     * Attempt to obtain an oauth2 token using a temporary (password reset or first time login) token.
     *
     * @return JsonResponse
     */

    public function tempToken(): JsonResponse
    {
        $params = request()->validate([
            'token' => 'required|string'
        ]);

        return UserAuthentication::attemptTemporaryTokenLogin($params['token'], false);
    }

    /**
     * Return all issued tokens for a person
     */

    public function tokens(Person $person): JsonResponse
    {
        Gate::allowIf(fn(Person $user) => $user->hasRole(Role::TECH_NINJA));

        $tokens = [];
        $expireMinutes = config('sanctum.expiration');
        foreach ($person->tokens()->get() as $token) {
            $tokens[] = [
                'id' => $token->id,
                'token' => $token->token,
                'last_used_at' => (string)$token->last_used_at,
                'created_at' => (string)$token->created_at,
                'expires_at' => (string)$token->created_at->addMinutes($expireMinutes),
                'name' => $token->name,
            ];
        }

        return response()->json(['tokens' => $tokens]);
    }

    /**
     * Revoke a token
     *
     * @param Person $person
     * @return JsonResponse
     */

    public function revokeToken(Person $person): JsonResponse
    {
        Gate::allowIf(fn(Person $user) => $user->hasRole(Role::TECH_NINJA));

        $params = request()->validate([
            'id' => 'required|integer|exists:personal_access_tokens,id'
        ]);

        $pat = PersonalAccessToken::findOrFail($params['id']);
        $pat->delete();
        ActionLog::record($person, 'oauth2-token-revoked', 'Token revoked', ['id' => $pat->id]);

        return response()->json(['status' => 'success']);
    }
}
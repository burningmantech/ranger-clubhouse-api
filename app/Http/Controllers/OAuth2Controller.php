<?php

namespace App\Http\Controllers;

use App\Models\OauthClient;
use App\Models\OauthCode;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OAuth2Controller extends ApiController
{
    public function openIdDiscovery(): JsonResponse
    {
        return response()->json([
            "issuer" => "https://ranger-clubhouse.burningman.org",
            "authorization_endpoint" => "http://127.0.0.1:4200/me/oauth2-grant",
            "token_endpoint" => "http://docker.for.mac.localhost:8000/auth/oauth2/token",
            "userinfo_endpoint" => "http://docker.for.mac.localhost:8000/auth/oauth2/userinfo",
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
                "locale",
                "name",
                "sub"
            ],
            "code_challenge_methods_supported" => [
                "plain",
                "S256"
            ],
            "grant_types_supported" => [
                "authorization_code",
                "refresh_token",
                "urn:ietf:params:oauth:grant-type:device_code",
                "urn:ietf:params:oauth:grant-type:jwt-bearer"
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
            throw new InvalidArgumentException('Unsupported response type');
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
            'grant_type' => 'required|string',
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
            'code' => 'required|string',
        ]);

        if ($params['grant_type'] != 'authorization_code') {
            throw new InvalidArgumentException('Grant type not supported.');
        }

        $client = OauthClient::findForClientId($params['client_id']);
        if (!$client) {
            throw new InvalidArgumentException('Client ID not registered.');
        }

        if ($client->secret != $params['client_secret']) {
            throw new InvalidArgumentException('Invalid client secret');
        }

        $oc = OAuthCode::findForClientCode($client, $params['code']);
        if (!$oc) {
            throw new InvalidArgumentException('Code not found.');
        }

        $person = Person::findOrFail($oc->person_id);

        $claims = [];
        if (!empty($oc->scope)) {
            foreach (explode(' ', $oc->scope) as $scope) {
                switch ($scope) {
                    case 'email':
                        $claims['email'] = $person->email;
                        break;
                    case 'profile':
                        $claims['given_name'] = $person->desired_first_name();
                        $claims['family_name'] = $person->last_name;
                        break;
                }
            }
        }

        $token = auth()->claims($claims)->login($person);
        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'expires_in' => (string)now()->addSeconds(config('jwt.ttl'))
        ]);
    }

    /**
     * Return basic user information for oauth2 tokens
     *
     * @return JsonResponse
     */

    public function oauthUserInfo(): JsonResponse
    {
        $person = Auth::user();

        return response()->json([
            'email' => $person->email,
            'given_name' => $person->desired_first_name(),
            'family_name' => $person->last_name
        ]);
    }
}
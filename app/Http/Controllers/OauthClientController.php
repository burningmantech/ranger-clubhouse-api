<?php

namespace App\Http\Controllers;

use App\Models\OauthClient;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class OauthClientController extends ApiController
{
    /**
     * Display a listing of the OAuth Clients.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $this->authorize('index', OauthClient::class);
        return $this->success(OauthClient::findAll(), null, 'oauth_client');
    }

    /***
     * Create an OAuth Client record
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', OauthClient::class);
        $oauthClient = new OauthClient;
        $this->fromRest($oauthClient);

        if ($oauthClient->save()) {
            return $this->success($oauthClient);
        }

        return $this->restError($oauthClient);
    }

    /**
     * Display the specified OAuth Client.
     *
     * @param OauthClient $oauthClient
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(OauthClient $oauthClient): JsonResponse
    {
        $this->authorize('show', $oauthClient);
        return $this->success($oauthClient);
    }

    /**
     * Update the specified OAuth Client in storage.
     *
     * @param OauthClient $oauthClient
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function update(OauthClient $oauthClient): JsonResponse
    {
        $this->authorize('update', $oauthClient);
        $this->fromRest($oauthClient);

        if ($oauthClient->save()) {
            return $this->success($oauthClient);
        }

        return $this->restError($oauthClient);
    }

    /**
     * Delete an OAuth Client record
     *
     * @param OauthClient $oauthClient
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(OauthClient $oauthClient): JsonResponse
    {
        $this->authorize('destroy', $oauthClient);
        $oauthClient->delete();
        return $this->restDeleteSuccess();
    }
}

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
        return $this->success(OauthClient::findAll());
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
        $client = new OauthClient;
        $this->fromRest($client);

        if ($client->save()) {
            return $this->success($client);
        }

        return $this->restError($client);
    }

    /**
     * Display the specified OAuth Client.
     *
     * @param OauthClient $client
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(OauthClient $client): JsonResponse
    {
        $this->authorize('show', $client);
        return $this->success($client);
    }

    /**
     * Update the specified OAuth Client in storage.
     *
     * @param OauthClient $client
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function update(OauthClient $client): JsonResponse
    {
        $this->authorize('update', $client);
        $this->fromRest($client);

        if ($client->save()) {
            return $this->success($client);
        }

        return $this->restError($client);
    }

    /**
     * Delete an OAuth Client record
     *
     * @param OauthClient $client
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(OauthClient $client): JsonResponse
    {
        $this->authorize('destroy', $client);
        $client->delete();
        return $this->restDeleteSuccess();
    }
}

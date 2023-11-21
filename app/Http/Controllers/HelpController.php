<?php

namespace App\Http\Controllers;

use App\Models\Help;
use App\Models\HelpHit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class HelpController extends ApiController
{
    /**
     * Display a listing of the helps.
     *
     * @return JsonResponse
     */

    public function index(): JsonResponse
    {
        return $this->success(Help::findAll(), null, 'help');
    }

    /***
     * Create a help record
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', [Help::class]);
        $help = new Help;
        $this->fromRest($help);

        if ($help->save()) {
            return $this->success($help);
        }

        return $this->restError($help);
    }

    /**
     * Display the specified help.
     *
     * @param Help $help
     * @return JsonResponse
     */

    public function show(Help $help): JsonResponse
    {
        HelpHit::record($help->id, Auth::id());

        return $this->success($help);
    }

    /**
     * Update the specified help in storage.
     *
     * @param Help $help
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function update(Help $help): JsonResponse
    {
        $this->authorize('update', $help);
        $this->fromRest($help);

        if ($help->save()) {
            return $this->success($help);
        }

        return $this->restError($help);
    }

    /**
     * @param Help $help
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(Help $help)
    {
        $this->authorize('destroy', $help);
        $help->delete();
        return $this->restDeleteSuccess();
    }
}

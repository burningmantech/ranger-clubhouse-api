<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;

use App\Models\Help;
use App\Models\HelpHit;

class HelpController extends ApiController
{
    /**
     * Display a listing of the helps.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        return $this->success(Help::findAll(), null, 'help');
    }

    /***
     * Store a newly created help in storage.
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */

    public function store()
    {
        $this->authorize('store', [ Help::class ]);
        $help = new Help;
        $this->fromRest($help);

        if ($help->save()) {
            return $this->success($help);
        }

        return $this->restError($help);
    }

    /**
     * Display the specified help.
     * @param Help $help
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Help $help)
    {
        $personId = $this->user ? $this->user->id : null;
        HelpHit::record($help->id, $personId);

        return $this->success($help);
    }

    /**
     * Update the specified help in storage.
     *
     * @param Help $help
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function update(Help $help)
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
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */

    public function destroy(Help $help)
    {
        $this->authorize('destroy', $help);
        $help->delete();
        return $this->restDeleteSuccess();
    }
}

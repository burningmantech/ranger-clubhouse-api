<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;

use App\Models\Motd;

class MotdController extends ApiController
{
    public function index()
    {
        return $this->success(Motd::findAll(), null, 'motd');
    }

    public function show(Motd $motd)
    {
        $this->authorize('show', Motd::class);
        return $this->success($motd);
    }

    /*
     * Create a new MOTD
     */

    public function store()
    {
        $this->authorize('create', Motd::class);

        $motd = new Motd;
        $this->fromRest($motd);
        $motd->person_id = $this->user->id;

        if (!$motd->save()) {
            return $this->restError($motd);
        }

        return $this->success($motd);
    }

    /*
     * Update a MOTD
     */
    public function update(Motd $motd)
    {
        $this->authorize('update', $motd);

        $this->fromRest($motd);

        if (!$motd->save()) {
            return $this->restError($motd);
        }

        return $this->success($motd);
    }

    /**
     * Remove a MOTD
     *
     */

    public function destroy(Motd $motd)
    {
        $this->authorize('destroy', $motd);

        $motd->delete();

        return $this->restDeleteSuccess();
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;

use App\Models\Motd;
use App\Models\PersonMotd;

use Illuminate\Support\Facades\DB;

class MotdController extends ApiController
{
    /**
     * Retrieve all announcements based on the given criteria
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */

    public function index()
    {
        $this->authorize('index', Motd::class);
        $params = request()->validate([
            'audience' => 'sometimes|string',
            'type' => 'sometimes|string',
            'expired' => 'sometimes|boolean',
            'page' => 'sometimes|integer',
            'page_size' => 'sometimes|integer',
            'sort' => 'sometimes|string',
        ]);

        $result = Motd::findForQuery($params);
        return $this->success($result['motd'], $result['meta'], 'motd');
    }

    /**
     * Retrieve the announcements for the current user.
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */

    public function bulletin()
    {
        $this->authorize('bulletin', Motd::class);
        $params = request()->validate([
            'type' => 'sometimes|string',
            'page' => 'sometimes|integer',
            'page_size' => 'sometimes|integer'
        ]);

        $result = Motd::findForBulletin($this->user->id, $this->user->status, $params);

        return $this->success($result['motd'], $result['meta'], 'motd');
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
     */

    public function destroy(Motd $motd)
    {
        $this->authorize('destroy', $motd);

        $motd->delete();
        DB::table('person_motd')->where('motd_id', $motd->id)->delete();

        return $this->restDeleteSuccess();
    }

    /**
     * Mark the motd as read by the current user
     *
     * @param Motd $motd
     * @return \Illuminate\Http\JsonResponse
     */

    public function markRead(Motd $motd)
    {
        PersonMotd::markAsRead($this->user->id, $motd->id);
        return $this->success();
    }
}

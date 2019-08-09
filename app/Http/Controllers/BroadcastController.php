<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;

use App\Models\Broadcast;
use App\Models\BroadcastMessage;
use App\Models\Role;

class BroadcastController extends ApiController
{
    public function __construct()
    {
        parent::__construct();

        if (!$this->userHasRole(Role::ADMIN)) {
            $this->notPermitted("Must have the Admin role");
        }
    }

    /**
     * Retrieve the broadcast logs
     */

    public function index()
    {
        $params = request()->validate([
            'year'  => 'required|integer',
            'failed' => 'sometimes|boolean'
        ]);

        $this->authorize('messages', [ Broadcast::class, $params['person_id'] ?? null]);

        return response()->json([ 'logs' => Broadcast::findLogs([ 'year' => $params['year'], 'failed' => ($params['failed'] ?? false) ]) ]);
    }

    /**
     * Show messages for person & year
     *
     */
    public function messages()
    {
        $params = request()->validate([
            'person_id' => 'sometimes|integer',
            'year'      => 'required|integer',
            'page'      => 'sometimes|integer',
            'page_size' => 'sometimes|integer',
            'status'    => 'sometimes|array',
            'status.*'  => 'sometimes|string',
            'direction' => 'sometimes|string'
        ]);

        $this->authorize('messages', [ Broadcast::class, $params['person_id'] ?? null]);

        return response()->json(BroadcastMessage::findForQuery($params));
    }
}

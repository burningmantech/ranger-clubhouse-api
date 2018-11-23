<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiController;
use App\Models\Broadcast;
use App\Models\BroadcastMessage;


class BroadcastController extends ApiController
{
    /**
     * Display a messages for person & year
     *
     */
    public function messages()
    {
        $params = request()->validate([
            'person_id' => 'required|integer',
            'year'      => 'required|integer',
        ]);

        $this->authorize('messages', [ Broadcast::class, $params['person_id']]);

        return response()->json([
            'messages'  => BroadcastMessage::findForPersonYear($params['person_id'], $params['year'])
        ]);
    }
}

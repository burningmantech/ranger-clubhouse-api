<?php

namespace App\Http\Controllers;

use App\Models\Broadcast;
use App\Models\BroadcastMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class BroadcastController extends ApiController
{
    /**
     * Retrieve the broadcast logs
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): \Illuminate\Http\JsonResponse
    {
        $params = request()->validate([
            'year' => 'required|integer',
            'failed' => 'sometimes|boolean'
        ]);

        $this->authorize('messages', [Broadcast::class, $params['person_id'] ?? null]);

        return response()->json(['logs' => Broadcast::findLogs(['year' => $params['year'], 'failed' => ($params['failed'] ?? false)])]);
    }

    /**
     * Show messages for person & year
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function messages(): JsonResponse
    {
        $params = request()->validate([
            'person_id' => 'sometimes|integer',
            'year' => 'required|integer',
            'page' => 'sometimes|integer',
            'page_size' => 'sometimes|integer',
            'status' => 'sometimes|array',
            'status.*' => 'sometimes|string',
            'direction' => 'sometimes|string'
        ]);

        $this->authorize('messages', [Broadcast::class, $params['person_id'] ?? null]);

        return response()->json(BroadcastMessage::findForQuery($params));
    }
}

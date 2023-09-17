<?php

namespace App\Http\Controllers;

use App\Models\Person;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class CallsignsController extends ApiController
{
    /**
     * Search for a callsign. Used primarily to send messages with.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $params = request()->validate([
            'query' => 'required|string',
            'type' => [
                'required',
                Rule::in(['message', 'contact', 'all'])
            ],
            'limit' => 'required|integer'
        ]);

        $type = $params['type'];

        if ($type == 'all') {
            $this->authorize('isAdmin');
        }

        return response()->json(['callsigns' => Person::searchCallsigns($params['query'], $type, $params['limit'])]);
    }
}

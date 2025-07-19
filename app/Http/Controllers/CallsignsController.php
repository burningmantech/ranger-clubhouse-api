<?php

namespace App\Http\Controllers;

use App\Lib\PersonSearch;
use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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
                Rule::in(['contact', 'message', 'all'])
            ],
        ]);

        $type = $params['type'];

        if ($type == 'all') {
            $this->authorize('isAdmin');
        } else {
            if ($type == 'contact') {
                Gate::denyIf(in_array($this->user->status, Person::NO_MESSAGES_STATUSES), 'You are not permitted to search for callsigns');
            } else {
                Gate::allowIf($this->user->hasRole(Role::EVENT_MANAGEMENT), 'You are not permitted to search for callsigns');
            }
        }

        return response()->json([
            'callsigns' => PersonSearch::searchCallsignsForMessaging($params['query'], $type)
        ]);
    }
}

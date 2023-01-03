<?php

/*
 * Clubhouse 1 Log controller - provided purely for historical purposes.
 *
 */

namespace App\Http\Controllers;

use App\Models\Clubhouse1Log;
use App\Models\Person;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class Clubhouse1LogController extends ApiController
{
    /**
     * Retrieve the action log
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $this->authorize('isAdmin');

        prevent_if_ghd_server('Clubhouse 1 Log viewing');

        $params = request()->validate([
            'sort' => 'sometimes|string',
            'events' => 'sometimes|string',
            'event_text' => 'sometimes|string',

            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date',

            'page' => 'sometimes|integer',
            'page_size' => 'sometimes|integer',

            'person' => 'sometimes|string',
        ]);

        $callsign = $params['person'] ?? null;
        if ($callsign) {
            if (is_numeric($callsign)) {
                $params['person_id'] = (int)$callsign;
            } else {
                $person = Person::findByCallsign($callsign);
                if (!$person) {
                    return response()->json(['error' => "Person $callsign was not found."]);
                }

                $params['person_id'] = $person->id;
            }
        }

        return response()->json(Clubhouse1Log::findForQuery($params));
    }
}

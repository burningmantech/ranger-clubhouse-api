<?php

namespace App\Http\Controllers;

use App\Http\RestApi;
use App\Models\ActionLog;
use App\Models\Person;
use App\Models\Role;
use Illuminate\Support\Facades\Log;

class ActionLogController extends ApiController
{
    /**
     * Retrieve the action log
     */

    public function index()
    {
        $this->authorize('index', ActionLog::class);

        $params = request()->validate([
            'sort' => 'sometimes|string',
            'events' => 'sometimes|array',

            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date',

            'page' => 'sometimes|integer',
            'page_size' => 'sometimes|integer',

            'person' => 'sometimes|string',
        ]);

        $redactData = !$this->userHasRole([Role::ADMIN, Role::VC]);

        $idOrCallsign = $params['person'] ?? null;
        if ($idOrCallsign) {
            if (is_numeric($idOrCallsign)) {
                $params['person_id'] = (int)$idOrCallsign;
            } else {
                $person = Person::findByCallsign($idOrCallsign);
                if (!$person) {
                    return RestApi::error(response(), 422, "Person $idOrCallsign was not found.");
                }
                $params['person_id'] = $person->id;
            }
        }

        $result = ActionLog::findForQuery($params, $redactData);
        return $this->success($result['action_logs'], $result['meta'], 'action_logs');
    }

    /**
     * Record analytics from the client
     *
     * Does not require authorization.
     */

    public function record()
    {
        $params = request()->validate([
            'person_id' => 'sometimes|integer',
            'target_person_id' => 'sometimes|integer',
            'data' => 'sometimes|string',
            'event' => 'required|string',
            'message' => 'sometimes|string'
        ]);

        $log = new ActionLog([
            'ip' => request_ip(),
            'user_agent' => request()->userAgent(),
            'person_id' => $params['person_id'] ?? null,
            'target_person_id' => $params['target_person_id'] ?? null,
            'event' => $params['event'],
            'data' => $params['data'] ?? null,
            'message' => $params['message'] ?? '',
        ]);
        $log->save();

        return response('success', 200);
    }

}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\ActionLog;
use App\Models\Person;
use App\Models\Role;

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

        if (isset($params['person'])) {
            $callsign = $params['person'];
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

        $data = $params['data'] ?? null;
        if (!$data) {
            $data = (object) [];
        } else {
            $data = json_decode($data);
        }

        if ($data) {
            $data->ip = request()->ip();
            $data->user_agent = request()->userAgent();
            $data = json_encode($data);
        }

        $log = new ActionLog([
            'person_id' => $params['person_id'] ?? null,
            'target_person_id' => $params['target_person_id'] ?? null,
            'event' => $params['event'],
            'data' => $data,
            'message' => $params['message'] ?? '',
        ]);
        $log->save();

        return response('success', 200);
    }

}

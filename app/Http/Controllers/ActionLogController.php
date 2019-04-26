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
             'sort'       => 'sometimes|string',
             'events'     => 'sometimes|array',

             'start_time'  => 'sometimes|date',
             'end_time'    => 'sometimes|date',

             'page'       => 'sometimes|integer',
             'page_size'  => 'sometimes|integer',

             'person'     => 'sometimes|string',
        ]);

        $redactData = !$this->userHasRole([ Role::ADMIN, Role::VC ]);

        if (isset($params['person'])) {
            $callsign = $params['person'];
            if (is_numeric($callsign)) {
                $params['person_id'] = (int) $callsign;
            } else {
                $person = Person::findByCallsign($callsign);
                if (!$person) {
                    return response()->json([ 'error' => "Person $callsign was not found."]);
                }

                $params['person_id'] = $person->id;
            }
        }

        return response()->json(ActionLog::findForQuery($params, $redactData));
    }

    /**
     * Record analytics from the client
     *
     * Does not require authorization.
     */

    public function record()
    {
        $data = request()->input('data');
        $personId = request()->input('person_id');
        $event = request()->input('event');
        $message = request()->input('message') ?? '';

        $log = new ActionLog([
            'event'     => $event,
            'person_id' => $personId,
            'data'      => $data,
            'message'   => $message,
        ]);
        $log->save();

        return response('success', 200);
    }

}

<?php

/*
 * Clubhouse 1 Log controller - provided purely for historical purposes.
 *
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\Clubhouse1Log;
use App\Models\Person;
use App\Models\Role;

class Clubhouse1LogController extends ApiController
{
    /**
     * Retrieve the action log
     */

     public function index()
     {
         $this->authorize('isAdmin');

         $params = request()->validate([
             'sort'       => 'sometimes|string',
             'events'     => 'sometimes|string',
             'event_text' => 'sometimes|string',

             'start_time'  => 'sometimes|date',
             'end_time'    => 'sometimes|date',

             'page'       => 'sometimes|integer',
             'page_size'  => 'sometimes|integer',

             'person'     => 'sometimes|string',
        ]);

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

        return response()->json(Clubhouse1Log::findForQuery($params));
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\Person;
use App\Models\PersonMessage;
use App\Models\Role;

use App\Lib\RBS;

class PersonMessageController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function index(Request $request)
    {
        $query = request()->validate([
            'person_id' => 'required|integer',
        ]);

        $personId = $query['person_id'];

        $this->authorize('index', [PersonMessage::class, $personId ]);

        return $this->success(PersonMessage::findForPerson($personId), null, 'person_message');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorize('store', [ PersonMessage::class ]);

        $person_message = new PersonMessage;
        $this->fromRest($person_message);

        // Message created by logged in user
        $person_message->creator_person_id = $this->user->id;

        if ($person_message->save()) {
            $person = Person::find($person_message->person_id);
            RBS::clubhouseMessageNotify($person, $this->user->id,
                $person_message->message_from, $person_message->subject, $person_message->body);
            return $this->success($person_message);
        }

        return $this->restError($person_message);
    }

    /**
     *  Delete a message
     *
     * @param  PersonMessage $person_message the message to delete
     * @return \Illuminate\Http\Response
     */
    public function destroy(PersonMessage $person_message)
    {
        $this->authorize('delete', $person_message);
        $person_message->delete();

        return $this->restDeleteSuccess();
    }

    /**
     * Mark message as read.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function markread(PersonMessage $person_message)
    {
        $this->authorize('markread', $person_message);

        if (!$person_message->markRead()) {
            return $this->restError('Cannot mark message as read');
        }

        return $this->success();
    }

}

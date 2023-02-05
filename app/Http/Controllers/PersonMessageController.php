<?php

namespace App\Http\Controllers;

use App\Lib\RBS;
use App\Models\PersonMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonMessageController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $query = request()->validate([
            'person_id' => 'required|integer',
        ]);

        $personId = $query['person_id'];

        $this->authorize('index', [PersonMessage::class, $personId]);

        return $this->success(PersonMessage::findForPerson($personId), null, 'person_message');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', [PersonMessage::class]);

        $person_message = new PersonMessage;
        $this->fromRest($person_message);

        // Message created by logged in user
        $person_message->creator_person_id = $this->user->id;

        if ($person_message->save()) {
            $person = $person_message->person;
            RBS::clubhouseMessageNotify($person, $this->user->id,
                $person_message->message_from, $person_message->subject, $person_message->body);
            return $this->success($person_message);
        }

        return $this->restError($person_message);
    }

    /**
     *  Delete a message
     *
     * @param PersonMessage $person_message the message to delete
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(PersonMessage $person_message): JsonResponse
    {
        $this->authorize('delete', $person_message);
        $person_message->delete();

        return $this->restDeleteSuccess();
    }

    /**
     * Mark message as read.
     *
     * @param PersonMessage $person_message
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function markread(PersonMessage $person_message): JsonResponse
    {
        $this->authorize('markread', $person_message);

        $params = request()->validate([
            'delivered' => 'required|boolean'
        ]);

        $person_message->delivered = $params['delivered'];
        if (!$person_message->save()) {
            return $this->restError($person_message);
        }

        return $this->success();
    }

}

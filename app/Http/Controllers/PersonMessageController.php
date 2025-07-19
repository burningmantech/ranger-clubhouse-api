<?php

namespace App\Http\Controllers;

use App\Lib\RBS;
use App\Models\PersonMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

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
        $this->authorize('store', PersonMessage::class);
        $person_message = new PersonMessage;
        $this->fromRest($person_message);

        if ($person_message->reply_to_id) {
            $replyTo = PersonMessage::find($person_message->reply_to_id);
            if (!$replyTo) {
                $person_message->addError('reply_to_id', 'Invalid reply to message id');
                return $this->restError($person_message);
            }
        }

        if ($person_message->save()) {
            $person = $person_message->person;
            RBS::clubhouseMessageNotify($person,
                $this->user->id,
                $person_message->message_from,
                $person_message->subject,
                $person_message->body,
                $person_message->message_type);
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
        $person_message->load('replies');
        foreach ($person_message->replies as $reply) {
            $reply->delete();
        }
        $person_message->delete();

        return $this->restDeleteSuccess();
    }

    /**
     * Mark a message as read.
     *
     * @param PersonMessage $person_message
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function markread(PersonMessage $person_message): JsonResponse
    {
        $params = request()->validate([
            'delivered' => 'required|boolean',
            'person_id' => 'required|integer',
        ]);

        $personId = $params['person_id'];
        $this->authorize('markread', [$person_message, $personId]);

        $person_message->delivered = $params['delivered'];
        $person_message->saveWithoutValidation();

        return $this->success($person_message);
    }
}

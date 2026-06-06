<?php

namespace App\Http\Controllers;

use App\Lib\RBS;
use App\Models\PersonMessage;
use App\Models\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
        $this->enforceSenderIdentity($person_message);

        if (!$person_message->save()) {
            return $this->restError($person_message);
        }

        $person = $person_message->person;
        RBS::clubhouseMessageNotify($person,
            $this->user->id,
            $person_message->message_from,
            $person_message->subject,
            $person_message->body,
            $person_message->message_type);

        return $this->success($person_message);
    }

    /**
     * Prevent sender spoofing. Only privileged users (granted blanket access by
     * PersonMessagePolicy::before) may attribute a message to another person, a team,
     * or the RBS. Everyone else is forced to send as themselves regardless of the
     * sender fields they supplied.
     *
     * @param PersonMessage $person_message
     * @return void
     */

    private function enforceSenderIdentity(PersonMessage $person_message): void
    {
        if ($this->user->hasRole([Role::ADMIN, Role::VC, Role::MESSAGE_MANAGEMENT])) {
            return;
        }

        $person_message->sender_type = PersonMessage::SENDER_TYPE_PERSON;
        $person_message->message_from = $this->user->callsign;
        $person_message->sender_team_id = null;
    }

    /**
     *  Delete a message and its replies atomically.
     *
     * @param PersonMessage $person_message the message to delete
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(PersonMessage $person_message): JsonResponse
    {
        $this->authorize('delete', $person_message);

        DB::transaction(function () use ($person_message) {
            $person_message->load('replies');
            foreach ($person_message->replies as $reply) {
                $reply->delete();
            }
            $person_message->delete();
        });

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

        $this->authorize('markread', $person_message);

        $person_message->delivered = $params['delivered'];
        $person_message->saveWithoutValidation();

        return $this->success($person_message);
    }
}

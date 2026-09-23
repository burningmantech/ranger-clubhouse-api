<?php

namespace App\Http\Controllers;

use App\Mail\ContactMail;
use App\Models\Alert;
use App\Models\AlertPerson;
use App\Models\ContactLog;
use App\Models\Person;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class ContactController extends ApiController
{
    /**
     * Send a contact message
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function send(): JsonResponse
    {
        $params = request()->validate([
            'recipient_id' => 'required|integer',
            'type' => 'required|string',
            'message' => 'required|string',
        ]);

        prevent_if_ghd_server('Ranger contact');

        $recipient = $this->findPerson($params['recipient_id']);
        $sender = $this->user;
        $type = $params['type'];
        $message = $params['message'];

        // The sender has to be active or inactive
        $status = $sender->status;
        if ($status != Person::ACTIVE && $status != Person::INACTIVE) {
            $this->notPermitted("User status [$status] is not permitted to send contact emails");
        }

        // The recipient has to be not suspended, and active or inactive
        $status = $recipient->status;
        if ($status != Person::ACTIVE && $status != Person::INACTIVE) {
            $this->notPermitted("Recipient status [$status] is not permitted to receive contact emails");
        }

        if ($type == 'mentor') {
            $subject = "Your mentor, Ranger {$sender->callsign}, wishes to get in contact.";
            $alertId = Alert::MENTOR_CONTACT;
            $action = 'mentee-contact';
        } else {
            $subject = "Ranger {$sender->callsign} wishes to get in contact.";
            $alertId = Alert::RANGER_CONTACT;
            $action = 'ranger-contact';
        }

        // And verify the recipient wants to be contacted
        if (!AlertPerson::allowEmailForAlert($recipient->id, $alertId)) {
            $this->notPermitted('recipient does not wish to be contacted');
        }

        if (!mail_send( new ContactMail($sender, $recipient, $subject, $message))) {
            return $this->error('Failed to send email');
        }

        ContactLog::record($sender->id, $recipient->id, $action, $recipient->email, $subject, $message);

        return $this->success();
    }

    /**
     * Formerly sent a message asking a human to update the mailing lists. Google Groups memberships are now
     * maintained automatically when the email address changes (see MailingListSync). Kept as a no-op until
     * the frontend stops calling it.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function updateMailingLists(Person $person): JsonResponse
    {
        $this->authorize('updateMailingLists', $person);
        return $this->success();
    }
}

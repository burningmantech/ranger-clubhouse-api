<?php

namespace App\Http\Controllers;

use App\Mail\ContactMail;
use App\Mail\UpdateMailingListSubscriptionsMail;
use App\Models\Alert;
use App\Models\AlertPerson;
use App\Models\ErrorLog;
use App\Models\Person;
use App\Models\PersonTeam;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use App\Exceptions\UnacceptableConditionException;

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
        } else {
            $subject = "Ranger {$sender->callsign} wishes to get in contact.";
            $alertId = Alert::RANGER_CONTACT;
        }

        // And verify the recipient wants to be contacted

        if (!AlertPerson::allowEmailForAlert($recipient->id, $alertId)) {
            $this->notPermitted('recipient does not wish to be contacted');
        }

        $mail = new ContactMail($sender, $recipient, $subject, $message);
        if (!mail_to_person($recipient, $mail, true)) {
            return $this->error('Failed to send email');
        }

        return $this->success();
    }

    /**
     * Send a message to request the mailing lists be updated.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function updateMailingLists(Person $person): JsonResponse
    {
        $this->authorize('updateMailingLists', $person);

        if (!in_array($person->status, Person::ACTIVE_STATUSES)) {
            throw new UnacceptableConditionException('Person does not have an active/current status');
        }

        $params = request()->validate([
            'old_email' => 'required|string',
            'message' => 'sometimes|string|max:1500'
        ]);

        $oldEmail = $params['old_email'];

        $email = setting('MailingListUpdateRequestEmail');
        if (empty($email)) {
            ErrorLog::record('update-mailing-lists-exception', [
                'message' => 'MailingListUpdateRequestEmail is not set',
                'person_id' => $person->id,
                'old_email' => $oldEmail
            ]);
            // Blindly fail.
            return $this->success();
        }

        $teams = PersonTeam::findAllTeamsForPerson($person->id);
        mail_to($email, new UpdateMailingListSubscriptionsMail($person, $this->user, $oldEmail, $params['message'] ?? '', $teams), true);
        return $this->success();
    }
}

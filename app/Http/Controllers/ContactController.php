<?php

namespace App\Http\Controllers;

use App\Mail\ContactMail;
use App\Mail\UpdateMailingListSubscriptionsMail;
use App\Models\Alert;
use App\Models\AlertPerson;
use App\Models\ContactLog;
use App\Models\ErrorLog;
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

    public function send()
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
            $this->notPermitted('sender is not active status');
        }

        // The recipient has to be not suspended, and active or inactive
        $status = $recipient->status;
        if ($status != Person::ACTIVE && $status != Person::INACTIVE) {
            $this->notPermitted('recipient is not active status');
        }

        if ($params['type'] == 'mentor') {
            $subject = "[rangers] Your mentor, Ranger {$sender->callsign}, wishes to get in contact.";
            $action = 'mentee-contact';
            $alertId = Alert::MENTOR_CONTACT;
        } else {
            $subject = "[rangers] Ranger {$sender->callsign} wishes to get in contact.";
            $action = 'ranger-contact';
            $alertId = Alert::RANGER_CONTACT;
        }

        // And verify the recipient wants to be contacted

        if (!AlertPerson::allowEmailForAlert($recipient->id, $alertId)) {
            $this->notPermitted('recipient does not wish to be contacted');
        }

        $mail = new ContactMail($sender, $recipient, $subject, $message);
        if (!mail_to($recipient->email, $mail, true)) {
            return $this->error('Failed to send email');
        }

        ContactLog::record($sender->id, $recipient->id, $action, $recipient->email, $subject, $message);

        return $this->success();
    }

    /**
     * Retrieve the contact logs for a person and year
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function showLog()
    {
        $this->authorize('isAdmin');

        $params = request()->validate([
            'person_id' => 'required|integer',
            'year' => 'required|integer',
        ]);

        $personId = $params['person_id'];
        $year = $params['year'];

        return response()->json([
            'sent_logs' => ContactLog::findForSenderYear($personId, $year),
            'received_logs' => ContactLog::findForRecipientYear($personId, $year)
        ]);
    }

    /**
     * Send a message to request the mailing lists be updated.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function updateMailingLists(Person $person)
    {
        $this->authorize('updateMailingLists', $person);

        if (!in_array($person->status, Person::ACTIVE_STATUSES)) {
            throw new \InvalidArgumentException('Person does not have an active/current status');
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

        mail_to($email, new UpdateMailingListSubscriptionsMail($person, $this->user, $oldEmail, $params['message'] ?? ''), true);
        return $this->success();
    }
}

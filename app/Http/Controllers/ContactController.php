<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Mail;

use App\Http\Controllers\ApiController;
use App\Mail\ContactMail;

use App\Models\Alert;
use App\Models\AlertPerson;
use App\Models\ContactLog;
use App\Models\Role;

class ContactController extends ApiController
{
    /*
     * Send a contact message
     */

     public function send()
     {
         $params = request()->validate([
            'recipient_id' => 'required|integer',
            'type'         => 'required|string',
            'message'      => 'required|string',
         ]);

         $recipient = $this->findPerson($params['recipient_id']);
         $sender = $this->user;
         $type = $params['type'];
         $message = $params['message'];

         // The sender has to be active or inactive
         $status = $sender->status;
         if ($status != 'active' && $status != 'inactive') {
             $this->notPermitted('sender is not active status');
         }

         // The recipient has to be not suspended, and active or inactive
         $status = $recipient->status;
         if (!$recipient->user_authorized || ($status != 'active' && $status != 'inactive')) {
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

         $mail = new ContactMail($sender, $recipient, $subject,  $message);
         if (!mail_to($recipient->email, $mail)) {
             return $this->error('Failed to send email');
         }

         ContactLog::record($sender->id, $recipient->id, $action, $recipient->email, $subject, $message);

         return $this->success();
     }

     /*
      * Retrieve a contact log
      */

      public function showLog() {
          $params = request()->validate([
              'person_id'   => 'required|integer',
              'year'        => 'required|integer',
          ]);

          if (!$this->userHasRole( Role::ADMIN)) {
              $this->notPermitted('User is not an admin.');
          }

          $personId = $params['person_id'];
          $year = $params['year'];


          return response()->json([
                'sent_logs' => ContactLog::findForSenderYear($personId, $year),
                'received_logs' =>  ContactLog::findForRecipientYear($personId, $year)
          ]);
      }
}

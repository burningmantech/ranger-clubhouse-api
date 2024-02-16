<?php

namespace App\Listeners;

use App\Models\MailLog;
use App\Models\ProspectiveApplication;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\DB;

class MailSentListener
{
    /**
     * Record all emails sent. A few hoops to be jumped because Laravel does seem to
     * have a way to attach any metadata to Mailable object which will bubbles
     * thru to the MailSentEvent. Use the sender & recipient addresses to associate
     * the mail with sender & recipient accounts.
     *
     * @param MessageSent $event
     * @return void
     */

    public function handle(MessageSent $event): void
    {
        $email = $event->message;
        $data = $event->data;
        $body = $email->getBody()->bodyToString();
        $from = $email->getFrom()[0]->getEncodedAddress();
        $subject = $email->getSubject();

        $senderId = $data['senderId'] ?? null;
        $application = $data['application'] ?? null;
        if (!is_a($application, ProspectiveApplication::class)) {
            // Does not appear to related to an application
            $application = null;
        }

        foreach ($email->getTo() as $to) {
            $toEmail = $to->getAddress();
            // Don't bother looking up team/cadre mailing lists emails.
            if (!preg_match('/^rangers?-[^@]*-(list|cadre|team)@([^@]*\.)?burningman\.(com|org)$/i', $toEmail)) {
                $personId = DB::table('person')->where('email', $toEmail)->value('id');
            } else {
                $personId = null;
            }

            MailLog::create([
                'person_id' => $personId,
                'sender_id' => $senderId,
                'to_email' => $toEmail,
                'from_email' => $from,
                'subject' => $subject,
                'body' => $body,
                'message_id' => $event->sent->getSymfonySentMessage()->getMessageId() ?? '',
                'prospective_application_id' => $application?->id,
            ]);
        }
    }
}

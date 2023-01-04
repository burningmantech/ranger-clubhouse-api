<?php

namespace App\Http\Controllers;

use App\Models\ActionLog;
use App\Models\ErrorLog;
use App\Models\MailLog;
use App\Models\Person;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class MailLogController extends ApiController
{
    /**
     * Retrieve the mail log
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $this->authorize('index', MailLog::class);

        $query = request()->validate([
            'person_id' => 'sometimes|integer',
            'year' => 'sometimes|digits:4',
            'page' => 'sometimes|integer',
            'page_size' => 'sometimes|integer'
        ]);

        $result = MailLog::findForQuery($query);
        return $this->success($result['mail_log'], $result['meta'], 'mail_log');
    }

    /**
     * Retrieve the stats for either the person or website
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function stats(): JsonResponse
    {
        $this->authorize('stats', MailLog::class);

        $query = request()->validate(['person_id' => 'sometimes|integer']);
        $personId = $query['person_id'] ?? null;

        return response()->json([
            'years' => MailLog::retrieveYears($personId),
            'counts' => MailLog::retrieveStats($personId),
        ]);
    }

    /**
     * Handle a SNS notification about a bounce or complaint
     *
     * See https://docs.aws.amazon.com/ses/latest/dg/notification-contents.html
     *
     * @return JsonResponse
     */

    public function snsNotification(): JsonResponse
    {
        $sns = json_decode(request()->getContent());

        // Look like two different message format might be seen, deal with both
        if (isset($sns->Type) && $sns->Type == 'SubscriptionConfirmation') {
            // Ping the URL provided to ack the subscription.
            try {
                $client = new Client;
                $client->get($sns->SubscribeURL);
            } catch (GuzzleException $e) {
                ErrorLog::recordException($e, 'sns-subscription-ping-exception', ['message' => $sns]);
            }
            return response()->json(['success' => true]);
        }

        if (!isset($sns->notificationType)) {
            ErrorLog::record('sns-unknown-format', ['body' => request()->getContent()]);
            return response()->json([], 200);
        }

        $messageId = $sns->mail->commonHeaders->messageId ?? "";

        $messageId = str_replace(['<', '>'], '', $messageId);

        switch ($sns->notificationType) {
            case 'Bounce':
                $bounceType = $sns->bounce->bounceType;
                $isPermanent = $bounceType == 'Permanent';
                foreach ($sns->bounce->bouncedRecipients as $to) {
                    if (!empty($messageId)) {
                        $mailLog = MailLog::markAsBounced($to->emailAddress, $messageId);
                    } else {
                        $mailLog = null;
                    }
                    $person = Person::findByEmail($to->emailAddress);
                    if ($isPermanent && $person) {
                        $person->is_bouncing = true;
                        $person->saveWithoutValidation();
                    }
                    ActionLog::record(null, 'email-bouncing', '', [
                        'to_email' => $to->emailAddress,
                        'message_id' => $messageId,
                        'bounce_type' => $bounceType,
                        'mail_log_id' => $mailLog?->id,
                        'message' => $sns,
                    ], $person?->id);
                }
                break;

            case 'Complaint':
                foreach ($sns->complaint->complainedRecipients as $to) {
                    if (!empty($messageId)) {
                        $mailLog = MailLog::markAsComplaint($to->emailAddress, $messageId);
                    } else {
                        $mailLog = null;
                    }
                    $person = Person::findByEmail($to->emailAddress);
                    ActionLog::record(null, 'email-complaint', '', [
                        'to_email' => $to->emailAddress,
                        'message_id', $messageId,
                        'mail_log_id' => $mailLog?->id,
                        'message' => $sns,
                    ], $person?->id);
                }
                break;

            default:
                ErrorLog::record('sns-message-unknown-type', ['message' => $body]);
                break;
        }

        return response()->json([], 200);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ErrorLog;
use App\Models\MailLog;
use Aws\Sns\Exception\InvalidSnsMessageException;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
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
     *
     */

    public function snsNotification(): JsonResponse
    {
        $message = Message::fromRawPostData();

        $validator = new MessageValidator();

        try {
            $validator->validate($message);
        } catch (InvalidSnsMessageException $e) {
            ErrorLog::recordException($e, 'sns-message-validation-exception', [
                'message' => $message
            ]);

            return response()->json([], 200);
        }

        $sns = json_decode(request()->getContent());

        // Temporary logging.. just until the things are stable.
        ErrorLog::record('sns-message-notification', ['message' => $sns]);

        switch ($sns->Type) {
            case 'Notification':
                $body = json_decode($sns->Message);
                $messageId = $body->mail->commonHeaders->messageId ?? "";

                if (empty($messageId)) {
                    ErrorLog::record('sns-message-no-id', [ 'message', $sns]);
                    return response()->json([], 200);
                }

                $messageId = str_replace(['<', '>'], '', $messageId);

                switch ($body->notificationType) {
                    case 'Bounce':
                        MailLog::markAsBounced($messageId);
                        break;

                    case 'Complaint':
                        MailLog::markAsComplaint($messageId);
                        break;

                    default:
                        ErrorLog::record('sns-message-unknown-type', ['message' => $body]);
                        break;
                }
                break;

            case 'SubscriptionConfirmation':
                // Ping the URL provided to ack the subscription.
                try {
                    $client = new Client;
                    $client->get($sns->SubscribeURL);
                } catch (GuzzleException $e) {
                    ErrorLog::recordException($e, 'sns-subscription-ping-exception', ['message' => $sns]);
                }
                return response()->json(['success' => true]);

            default:
                ErrorLog::record('sns-notification-unknown-type', ['message' => $sns]);
                break;
        }

        return response()->json([], 200);
    }
}

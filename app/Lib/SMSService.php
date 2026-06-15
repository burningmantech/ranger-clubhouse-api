<?php

/*
 * SMS Services provided by Twilio.
 *
 * Handle sending  SMS broadcasts
 */

namespace App\Lib;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use RuntimeException;
use Twilio\TwiML\MessagingResponse;

class SMSService
{
    // Do not reply to stop replies
    const NO_STOP_REPLIES = 1;

    /*
     * Retrieve the authentication tokens needed
     */

    public static function getTokens()
    {
        $sid = setting('TwilioAccountSID');
        if (empty($sid)) {
            throw new RuntimeException('TwilioAccountSID is not configured');
        }

        $authToken = setting('TwilioAuthToken');
        if (empty($authToken)) {
            throw new RuntimeException('TwilioAuthToken is not configured');
        }

        return [$sid, $authToken];
    }

    /*
     * Outbound SMS (broadcast) and carrier lookup (isSMSCapable) now live behind the
     * SmsGateway seam. Resolve with app(SmsGateway::class). Adapters: TwilioSmsGateway
     * (production), Tests\Support\FakeSmsGateway (tests). The pure helpers below — token
     * lookup, phone normalization, incoming/response shaping — stay here.
     */

    /*
     * Process an incoming messages into a common format.
     *
     * The following associate array is returned:
     *
     * 'phone' = The phone number sending the message
     * 'message' = The incoming message
     * 'raw'    = all the other post fields in json format
     *
     */

    public static function processIncoming($request): object
    {
        return (object)[
            'phone' => $request->input('From') ?? '',
            'message' => $request->input('Body') ?? '',
            'raw' => json_encode($request->all())
        ];
    }

    /*
     * Process a status callback. (currently unused.)
     */

    public static function processStatusCallback(): array
    {
        $statusCode = Input::get('MessageStatus');

        switch ($statusCode) {
            case 'delivered':
                $status = 'delivered';
                break;

            case 'undelivered':
            case 'failed':
                $status = 'failed';
                break;

            default:
                $status = 'sent';
                break;
        }

        if ($status == 'failed') {
            $errorCode = getRequest('ErrorCode');
            switch ($errorCode) {
                case 30004: // phone maybe black listed
                    $status = 'blocked';
                    break;

                case 30005: // unreachable phone
                case 30006: // a landline
                case 30007:
                    $status = 'bounced';
                    break;

            }
        }

        return [
            'status' => $status,
            'message' => json_encode($_POST),
            'broadcast_id' => getRequest('broadcast_id'),
            'phone' => getRequest('To')
        ];
    }

    /*
     * Format a response
     */

    public static function replyResponse($reply)
    {
        if (empty($reply)) {
            return response('', 200);
        }

        $response = new MessagingResponse();
        $response->message($reply);
        return response((string)$response, 200)->header('Content-Type', 'text/xml');
    }

    /*
     * Normalize a phone number to the E164 format for use by Twilio.
     *  - number needs to start with a '+'
     *  - number needs to have a country code
     *  - 10 chars long with no + prefix assumes to be a US/Canadian number.
     *
     * @param string $phone Number to normalize
     * @return string normalized number
     *
     */

    public static function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        $normalized = preg_replace("/\W+/", "", $phone);
        $len = strlen($normalized);

        $country = null;
        if ($len == 10 || (str_starts_with($phone, '1') && $len == 11)) {
            $country = 'US';
        }

        $util = PhoneNumberUtil::getInstance();
        try {
            $parsedPhone = $util->parse($phone, $country);
            return $util->format($parsedPhone, PhoneNumberFormat::E164);
        } catch (NumberParseException $e) {
            return $phone;
        }
    }
}

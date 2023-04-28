<?php

/*
 * SMS Services provided by Twilio.
 *
 * Handle sending  SMS broadcasts
 */

namespace App\Lib;

use Exception;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use RuntimeException;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Exceptions\RestException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;
use Twilio\TwiML\MessagingResponse;

class SMSException extends Exception
{
}

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
     * Broadcast a message to set of phones.
     *
     * @param array $phoneNumbers list of phone numbers to spam
     * @param string $message Text to send.
     * @throw SMSException if Twilio experienced an error.
     */

    /**
     * @throws SMSException
     */
    public static function broadcast($phoneNumbers, $message): string
    {
        list ($accountSid, $authToken) = SMSService::getTokens();

        $serviceIds = setting('TwilioServiceId');
        if (empty($serviceIds)) {
            throw new RuntimeException('TwilioServiceId is not configured');
        }
        $serviceIds = explode(',', $serviceIds);

        $bindings = [];
        // Build up request - normalize the numbers
        foreach ($phoneNumbers as $phone) {
            $bindings[] = json_encode([
                'binding_type' => 'sms',
                'address' => self::normalizePhone($phone)
            ]);
        }

        $serviceCount = count($serviceIds);
        $chunkSize = intval((count($bindings) + ($serviceCount - 1)) / $serviceCount);
        $bindingChunks = array_chunk($bindings, $chunkSize);

        foreach ($serviceIds as $idx => $serviceId) {
            try {
                // The Twilio notify API will not produce an error when
                // an invalid phone number is given.
                $twilio = new Client($accountSid, $authToken);
                $notification = $twilio->notify->v1->services($serviceId)->notifications;
                $params = [
                    'body' => $message,
                    'toBinding' => $bindingChunks[$idx],
                ];
                $response = $notification->create($params);
            } catch (TwilioException $e) {
                throw new SMSException($e->getMessage());
            }
        }

        return 'sent';
    }

    /**
     * Try to find out if a phone number can text, maybe.
     *
     * Note this may only work for US numbers.
     *
     * https://support.twilio.com/hc/en-us/articles/360004563433
     *
     * @param string $phoneNumber number to look up in E.164 format
     * @return bool true if the phone can text.
     * @throws RestException
     * @throws TwilioException
     * @throws ConfigurationException
     */

    public static function isSMSCapable($phoneNumber): bool
    {
        list ($accountSid, $authToken) = SMSService::getTokens();

        $twilio = new Client($accountSid, $authToken);

        try {
            $info = $twilio->lookups->v1->phoneNumbers($phoneNumber)
                ->fetch(["type" => "carrier"]);
        } catch (RestException $e) {
            if ($e->getStatusCode() == 404) {
                return false; // Not a valid phone number
            }

            throw $e; // Not sure what's going on.
        }

        $type = $info->carrier['type'];
        // In case there's no line type, allow it to pass. The verification
        // stage will flush that out.
        return ($type == 'mobile' || $type == 'voip' || $type == '');
    }

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

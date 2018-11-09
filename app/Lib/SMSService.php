<?php

/*
 * SMS Services provided by Twilio.
 *
 * Handle sending  SMS broadcasts
 */

namespace App\Lib;

class SMSException extends \Exception
{
    public $status;
    public function __construct($status)
    {
        $this->status = $status;
    }

    public function __toString()
    {
        return "Twilio HTTP request status [{$this->status}]";
    }
};

class SMSService
{
    // Do not reply to stop replies
    const NO_STOP_REPLIES = 1;

    /*
     * Retrieve the authenitication tokens needed
     */

    public static function getTokens()
    {
        $sid = config('clubhouse.TwilioAccountSID');
        if (empty($sid)) {
            throw new \RuntimeException('TwilioAccountSID is not configured');
        }

        $authToken = config('clubhouse.TwilioAuthToken');
        if (empty($authToken)) {
            throw new \RuntimeException('TwilioAuthToken is not configured');
        }

        return [ $sid, $authToken ];
    }

    /*
     * Broadcast a message to set of phones.
     *
     * @param array $phoneNumbers list of phone numbers to spam
     * @param string $message Text to send.
     * @throw SMSException if Twilio experienced an error.
     */

    public static function broadcast($phoneNumbers, $message)
    {
        list ($accountSid, $authToken) = SMSService::getTokens();

        $serviceIds = config('clubhouse.TwilioServiceId');
        if (empty($serviceIds)) {
            throw new \RuntimeException('TwilioServiceId is not configured');
        }
        $serviceIds = explode(',', $serviceIds);

        $callbackUrl = config('TwilioStatusCallbackUrl');

        $bindings = [];
        // Build up request - normalize the numbers
        foreach ($phoneNumbers as $phone) {
            $bindings[] = json_encode([
                'binding_type' => 'sms',
                'address'      => self::normalizePhone($phone)
            ]);
        }

        $serviceCount = count($serviceIds);
        $chunkSize = intval((count($bindings) + ($serviceCount-1))/$serviceCount);
        $bindingChunks = array_chunk($bindings, $chunkSize);

        foreach ($serviceIds as $idx => $serviceId) {
            try {
                // The Twilio notify API will not produce an error when
                // an invalid phone number is given.
                $twilio = new \Twilio\Rest\Client($accountSid, $authToken);
                $notification = $twilio->notify->v1->services($serviceId)->notifications;
                $params = [
                    'body'      => $message,
                    'toBinding' => $bindingChunks[$idx],
                ];

                if ($callbackUrl != '') {
                    $params['sms'] = json_encode([ 'status_callback' => $callbackUrl ]);
                }

                $response = $notification->create($params);
            } catch (\Twilio\Exceptions\TwilioException $e) {
                throw new SMSException($log);
            }
        }

        return 'sent';
    }

    /*
     * Try find out if a phone number can text, maybe.
     *
     * Note this may only work for US numbers.
     *
     * https://support.twilio.com/hc/en-us/articles/360004563433
     *
     * @param string $phoneNumber number to look up in E.164 format
     * @return bool true if the phone can text.
     */

     public static function isSMSCapable($phoneNumber)
     {
         list ($accountSid, $authToken) = SMSService::getTokens();

         $twilio = new \Twilio\Rest\Client($accountSid, $authToken);

         $info = $twilio->lookups->v1->phoneNumbers($phoneNumber)
                                ->fetch([ "type" => "carrier" ]);

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

    public static function processIncoming()
    {
        return [
            'phone'   => Input::get('From'),
            'message' => Input::get('Body'),
            'raw'     => json_encode($_POST)
        ];
    }

    /*
     * Process a status callback. (currently unused.)
     */

    public static function processStatusCallback()
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
            'status'        => $status,
             'message'      => json_encode($_POST),
             'broadcast_id' => getRequest('broadcast_id'),
             'phone'        => getRequest('To')
         ];
    }

    /*
     * Format a response
     */

    public static function replyResponse($reply)
    {
        header("Content-Type: text/xml");

        $response = new \Twilio\Twiml();
        $response->message($reply);
        echo $response;
    }

    /*
     * Normalize a phone number for use by Twilio.
     *  - number needs to start with a '+'
     *  - number needs to have a country code
     *  - 10 chars long with no + prefix assumes to be a US/Canadian number.
     *
     * @param string $phone Number to normalize
     * @return string normalized number
     *
     */

    public static function normalizePhone($phone)
    {
        // Assume + the number good to go
        if (substr($phone, 0, 1) == '+') {
            return $phone;
        }

        // Assume USA/Canada
        if (strlen($phone) == 10) {
            return '+1'.$phone;
        }

        return '+'.$phone;
    }
};

<?php

namespace App\Lib;

use RuntimeException;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Exceptions\RestException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

/**
 * Production SmsGateway adapter backed by the Twilio SDK.
 */

class TwilioSmsGateway implements SmsGateway
{
    /**
     * @param string[] $phoneNumbers
     * @param string $message
     * @return string
     * @throws SMSException
     */

    public function broadcast(array $phoneNumbers, string $message): string
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
                'address' => SMSService::normalizePhone($phone)
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
                $notification->create($params);
            } catch (TwilioException $e) {
                throw new SMSException($e->getMessage());
            }
        }

        return 'sent';
    }

    /**
     * @param string $phoneNumber
     * @return bool
     * @throws RestException
     * @throws TwilioException
     * @throws ConfigurationException
     */

    public function isSMSCapable(string $phoneNumber): bool
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
}

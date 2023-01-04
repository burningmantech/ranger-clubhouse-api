<?php

namespace App\Http;

use Illuminate\Support\MessageBag;

class RestApi
{
    /*
     * construct a REST API error response
     * @var object $response response() to send JSON
     * @var integer $status the HTTP status code to send back
     * @var (array|string) $errorMessages a string or array of strings to send back
     */
    public static function error($response, $status, $errorMessages)
    {
        $errorRows = [];

        if ($errorMessages instanceof MessageBag) {
            foreach ($errorMessages->getMessages() as $field => $messages) {
                if (!is_array($messages)) {
                    $messages = [$messages];
                }

                foreach ($messages as $message) {
                    $errorRows[] = [
                        'status' => '422',
                        'title' => $message,
                        'source' => [
                            'pointer' => "/data/attributes/$field"
                        ],
                    ];
                }
            }
        } else {
            if (!is_array($errorMessages)) {
                $errorMessages = [$errorMessages];
            }

            foreach ($errorMessages as $message) {
                $errorRows[] = [
                    'title' => $message
                ];
            }
        }

        return $response->json(['errors' => $errorRows], $status);
    }
}

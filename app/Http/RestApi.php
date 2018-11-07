<?php

namespace App\Http;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class RestApi {
    /*
     * construct a REST API error response
     * @var object $response response() to send JSON
     * @var integer $status the HTTP status code to send back
     * @var (array|string) $errorMessages a string or array of strings to send back
     */
    public static function error($response, $status, $errorMessages)
    {
        $errorRows = [ ];

        if (!is_array($errorMessages)) {
            $errorMessages = [ $errorMessages ];
        }

        foreach ($errorMessages as $message) {
            $errorRows[] = [
                'title'  => $message
            ];
        }

        return $response->json([ 'errors' => $errorRows ], $status);
    }
}

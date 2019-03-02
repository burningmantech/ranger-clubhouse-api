<?php

namespace App\Models;

/*
 * Heavily borrowed from:
 * https://developers.google.com/sheets/api/quickstart/php
 *
 * This class sets up a connection to a Google sheet, using
 * previously stored credentials, and allows you to query a
 * particular range of the spreadsheet.
 */

use Illuminate\Support\Facades\Config;

class ManualReviewGoogle
{

    private $googleClient;
    private $spreadsheet;

    const APPLICATION_NAME = 'Ranger Secret Clubhouse Manual Review';

    /**
     * Returns an authorized API client.
     *
     * @return Google_Client the authorized client object
     */
    function getGoogleClient()
    {
        $authConfig = setting('ManualReviewAuthConfig');

        if (empty($authConfig)) {
            throw new \Exception("ManualReviewAuthConfig is not set.");
        } else {
            $authConfig = json_decode($authConfig, true);
        }

        $client = new \Google_Client();
        $client->setApplicationName(self::APPLICATION_NAME);

        $scopes = implode(' ', array(\Google_Service_Sheets::SPREADSHEETS_READONLY));
        $client->setScopes($scopes);
        $client->setAuthConfig($authConfig);
        $client->setAccessType('offline');

        return $client;
    }

    function connect()
    {
        // Get the API client and construct the service object.
        $this->client = $this->getGoogleClient();
        $this->service = new \Google_Service_Sheets($this->client);
    }

    function getRange($spreadsheetId, $range)
    {
        $response = $this->service->spreadsheets_values->get($spreadsheetId, $range);
        $values = $response->getValues();
        return $values;
    }

    function getResults()
    {
        $spreadsheetId = setting('ManualReviewGoogleSheetId');
        $range = 'Form Responses 1!A1:B';
        $values = $this->getRange($spreadsheetId, $range);
        return $values;
    }

};

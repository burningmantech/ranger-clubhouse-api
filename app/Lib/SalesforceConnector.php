<?php

namespace App\Lib;

/*
 * Generic interface to Salesforce via their simplest RESTful API.
 */

use App\Models\ErrorLog;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use JsonException;

class SalesforceConnector
{

    // These are set by the response from Salesforce at auth time
    private $instanceurl;

    private $access_token;
    public $errorMessage;

    /**
     * Authenticate us with the Salesforce server.
     * Return TRUE on success, FALSE on failure.
     * On success, this->access_token is set to the
     * access token we need to present to SF in future requests.
     *
     * @return bool
     */

    public function auth(): bool
    {
        if (empty(setting("SFprdPassword"))) {
            $this->errorMessage = "SFprdPassword setting is empty ";
            return false;
        }

        $authUrl = setting("SFprdAuthUrl");

        $client = new Client(['base_uri' => $authUrl]);
        try {
            $response = $client->request('POST', 'services/oauth2/token', [
                RequestOptions::FORM_PARAMS => [
                    'grant_type' => 'password',
                    'client_id' => setting("SFprdClientId"),
                    'client_secret' => setting("SFprdClientSecret"),
                    'username' => setting("SFprdUsername"),
                    'password' => setting("SFprdPassword"),
                ]
            ]);
        } catch (GuzzleException $e) {
            $this->errorMessage = "Failed to retrieve authentication token: " . $e->getMessage();
            ErrorLog::recordException($e, 'salesforce-auth-exception', ['auth_url' => $authUrl]);
            return false;
        }

        try {
            $result = json_decode($response->getBody(), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            ErrorLog::recordException($e, 'salesforce-json-exception', ['body' => $response->getBody()]);
            $this->errorMessage = "json_decode failed on Salesforce token response: " . $e->getMessage();
            return false;
        }

        if (!empty($result->error)) {
            ErrorLog::record('salesforce-request-exception', ['result' => $result]);
            $this->errorMessage = "Salesforce authentication failed: "
                . $result->error . ": " . $result->error_description;
            return false;
        }

        $this->access_token = $result->access_token;
        $this->instanceurl = $result->instance_url;

        return true;
    }

    /**
     * Execute a Salesforce query.  Return a decoded object on success, FALSE on failure.
     * @param string $q SOSQL to execute
     * @return object|false
     */

    public function soqlQuery(string $q): object|false
    {
        $client = new Client(['base_uri' => $this->instanceurl]);

        try {
            $response = $client->request('GET', 'services/data/v57.0/query', [
                RequestOptions::HEADERS => ['Authorization' => 'Bearer ' . $this->access_token],
                RequestOptions::QUERY => ['q' => $q]
            ]);
        } catch (GuzzleException $e) {
            ErrorLog::recordException($e, 'salesforce-query-exception', ['query' => $q]);
            $this->errorMessage = "Salesforce SOQL error: " . $e->getMessage();
            return false;
        }

        try {
            $result = json_decode($response->getBody(), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->errorMessage = "Salesforce SOQL JSON decode error: " . $e->getMessage();
            ErrorLog::recordException($e, 'salesforce-json-exception', ['body' => $response->getBody()]);
            return false;
        }

        // If it's not an object, presumably the query failed.
        if (!is_object($result)) {
            if (is_array($result) && isset($result[0]->errorCode)) {
                $this->errorMessage = "Salesforce SOQL query failed: "
                    . $result[0]->errorCode . ": " . $result[0]->message;
            } else {
                $this->errorMessage = "Salesforce SOQL query failed; something funny happened and the result is neither an array nor an object.";
            }
            ErrorLog::record('salesforce-query-failed', ['query' => $q, 'result' => $result]);
            return false;
        }

        return $result;
    }

    /**
     * Update the Salesforce object of $id.
     * Fields is an array of name -> value to be updated.
     * Returns TRUE on success, FALSE on error.
     *
     * @param string $objname
     * @param string $objid
     * @param array $fields
     * @return bool
     */

    public function objUpdate(string $objname, string $objid, array $fields): bool
    {
        $client = new Client(['base_uri' => $this->instanceurl]);
        try {
            $response = $client->request('PATCH', "services/data/v51.0/sobjects/$objname/$objid", [
                RequestOptions::HEADERS => ['Authorization' => 'Bearer ' . $this->access_token],
                RequestOptions::JSON => $fields
            ]);
        } catch (GuzzleException $e) {
            ErrorLog::recordException($e, 'salesforce-update-exception',
                [
                    'objname' => $objname,
                    'objid' => $objid,
                    'fields' => $fields
                ]);
            $this->errorMessage = "objUpdate Request error: " . $e->getMessage();
            return false;
        }

        return true;
    }
}

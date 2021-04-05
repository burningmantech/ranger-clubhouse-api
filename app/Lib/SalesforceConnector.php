<?php

namespace App\Lib;

/*
 * Generic interface to Salesforce via their simplest RESTful API.
 */

use App\Models\ErrorLog;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Log;
use JsonException;

class SalesforceConnector
{

    // These are set via methods below
    private $client_id;
    private $client_secret;
    private $security_token;
    private $username;
    private $password;
    private $auth_url;

    private $debug = 0;                 // Enables html output debugging

    // This is set by the response from Salesforce at auth time
    private $instanceurl;

    private $access_token;
    public $errorMessage;

    public function setClientID(string $id)
    {
        $this->client_id = $id;
    }

    public function setClientSecret(string $secret)
    {
        $this->client_secret = $secret;
    }

    public function setUsername(string $username)
    {
        $this->username = $username;
    }

    public function setPassword(string $password)
    {
        $this->password = $password;
    }

    public function setSecurityToken(string $token)
    {
        $this->security_token = $token;
    }

    public function setAuthURL($url)
    {
        $this->auth_url = $url;
    }

    public function setDebug($val)
    {
        $this->debug = $val;
    }

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
        $client = new Client(['base_uri' => $this->auth_url]);
        try {
            $response = $client->request('POST', 'services/oauth2/token', [
                RequestOptions::FORM_PARAMS => [
                    'grant_type' => 'password',
                    'client_id' => $this->client_id,
                    'client_secret' => $this->client_secret,
                    'username' => $this->username,
                    'password' => $this->password,
                ]
            ]);
        } catch (GuzzleException $e) {
            $this->errorMessage = "SOQL Request error: " . $e->getMessage();
            ErrorLog::recordException($e, 'salesforce-auth-exception', ['auth_url' => $this->auth_url]);
            return false;
        }

        try {
            $result = json_decode($response->getBody(), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            ErrorLog::recordException($e, 'salesforce-json-exception', ['body' => $response->getBody()]);
            $this->errorMessage = "json_decode failed: " . $e->getMessage();
            return false;
        }

        if (!empty($result->error)) {
            ErrorLog::record('salesforce-request-exception', ['result' => $result]);
            $this->errorMessage = "Salesforce authentication failed: "
                . $result->error . ": " . $result->error_description;
            if ($this->debug) {
                Log::debug("sf->auth failed: " . $this->errorMessage);
            }
            return false;
        }

        $this->access_token = $result->access_token;
        $this->instanceurl = $result->instance_url;
        if ($this->debug) {
            Log::debug("sf->auth: access token = " . $this->access_token);
            Log::debug("sf->auth: instanceurl = " . $this->instanceurl);
        }

        return true;
    }

    /**
     * Execute a Salesforce query.  Return a decoded object on success, FALSE on failure.
     * @param string $q SOSQL to execute
     * @return mixed
     */

    public function soqlQuery(string $q) : mixed
    {
        if ($this->debug) {
            Log::debug("soqlQuery: q = " . $url);
        }

        $client = new Client(['base_uri' => $this->instanceurl]);

        try {
            $response = $client->request('GET', 'services/data/v51.0/query', [
                RequestOptions::HEADERS => ['Authorization' => 'Bearer ' . $this->access_token],
                RequestOptions::QUERY => ['q' => $q]
            ]);
        } catch (GuzzleException $e) {
            ErrorLog::recordException($e, 'salesforce-query-exception', ['query' => $q]);
            $this->errorMessage = "SOQL Request error: " . $e->getMessage();
            return false;
        }

        try {
            $result = json_decode($response->getBody(), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->errorMessage = "JSON decode error: " . $e->getMessage();
            ErrorLog::recordException($e, 'salesforce-json-exception', ['body' => $response->getBody()]);
            return false;
        }

        // If it's not an object, presumably the query failed.
        if (!is_object($result)) {
            if (is_array($result) && isset($result[0]->errorCode)) {
                $this->errorMessage = "SOQL query failed: "
                    . $result[0]->errorCode . ": " . $result[0]->message;
            } else {
                $this->errorMessage = "SOQL query failed; something funny happened and the result is neither an array nor an object.";
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
     * @return bool
     */

    public function objUpdate($objname, $objid, $fields): bool
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

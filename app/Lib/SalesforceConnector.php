<?php

namespace App\Lib;

/*
 * Generic interface to Salesforce via their simplest RESTful API.
 */

class SalesforceConnector
{

    // These are set via methods below
    private $client_id;
    private $client_secret;
    private $username;
    private $password;
    private $authurl;

    private $debug = 0;                 // Enables html output debugging
    private $connect_timeout = 3;       // Seconds
    private $response_timeout = 30;     // Seconds

    // This is set by the response from Salesforce at auth time
    private $instanceurl;

    private $access_token;
    public $errorMessage;

    public function __construct()
    {
    }

    public function setClientID($id)
    {
        $this->client_id = $id;
    }

    public function setClientSecret($secret)
    {
        $this->client_secret = $secret;
    }

    public function setUsername($username)
    {
        $this->username = $username;
    }

    public function setPassword($password)
    {
        $this->password = $password;
    }

    public function setAuthURL($url)
    {
        $this->authurl = $url;
    }

    public function setDebug($val)
    {
        $this->debug = $val;
    }

    /*
    * Authenticate us with the Salesforce server.
    * Return TRUE on success, FALSE on failure.
    * On success, this->access_token is set to the
    * access token we need to present to SF in future requests.
    */
    public function auth()
    {
        $url = $this->authurl;
        $fields = [
            'grant_type' => urlencode('password'),
            'client_id' => urlencode($this->client_id),
            'client_secret' => urlencode($this->client_secret),
            'username' => $this->username,
            'password' => $this->password,
        ];

        if ($this->debug) {
            Log::debug(
                "sf->auth:\n"
                . "\nclient_id = " . $this->client_id
                . "\nclient_secret = " . $this->client_secret
                . "\nusername = " . $this->username
                . "\npassword = " . $this->password
                . "\nauth url = " . $url
                . "\n"
            );
        }

        $postparams = http_build_query($fields);

        $ch = curl_init();
        $this->setCommonCurlOptions($ch);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postparams);
        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($this->debug) {
            Log::debug("DEBUG sf->auth: result =", $result);
            Log::debug("DEBUG: error =", $error);
        }

        if ($result == false) {
            $this->errorMessage = "curl_exec failed: " . $error;
            if ($this->debug) {
                Log::debug("DEBUG sf->auth failed: " . $this->errorMessage);
            }
            return false;
        }

        $r = json_decode($result);
        if ($r == null) {
            $this->errorMessage = "json_decode failed: " .
                json_last_error_message();
            if ($this->debug) {
                Log::debug("EBUG sf->auth failed: " . $this->errorMessage);
            }
            return false;
        }

        if (isset($r->error) && $r->error != "") {
            $this->errorMessage = "Salesforce authentication failed: "
                . $r->error . ": " .$r->error_description;
            if ($this->debug) {
                Log::debug("sf->auth failed: " . $this->errorMessage);
            }
            return false;
        }

        $this->access_token = $r->access_token;
        $this->instanceurl = $r->instance_url;
        if ($this->debug) {
            Log::debug("sf->auth: access token = " . $this->access_token);
            Log::debug("sf->auth: instanceurl = " . $this->instanceurl);
        }

        return true;
    }

    /*
    * Execute a Salesforce query.  Return a decoded object on
    * success, FALSE on failure.
    */
    public function soqlQuery($q)
    {
        $url = $this->instanceurl . "/services/data/v32.0/query?q="
                . urlencode($q);

        if ($this->debug) {
            Log::debug("soqlQuery: q = " . $url);
        }

        $ch = curl_init();
        $this->setCommonCurlOptions($ch);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array("Authorization: Bearer $this->access_token")
        );
        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result == false) {
            $this->errorMessage = "curl_exec failed: " . $error;
            return false;
        }

        $r = json_decode($result);
        if ($r == null) {
            $this->errorMessage = "json_decode failed: " .
                json_last_error_message();
            return false;
        }
        // If it's not an object, presumably the query failed.
        if (!is_object($r)) {
            if (is_array($r) && isset($r[0]->errorCode)) {
                $this->errorMessage = "SOQL query failed: "
                    . $r[0]->errorCode . ": " . $r[0]->message;
            } else {
                $this->errorMessage = "SOQL query failed; something funny happened and the result is neither an array nor an object.";
            }
            return false;
        }

        return $r;
    }

    /*
    * Update the Salesforce object of $id.
    * Fields is an array of name -> value to be updated.
    * Returns TRUE on success, FALSE on error.
    */
    public function objUpdate($objname, $objid, $fields)
    {
        $url = $this->instanceurl
                    . "/services/data/v32.0/sobjects/$objname/$objid";

        $ch = curl_init();
        $this->setCommonCurlOptions($ch);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array("Authorization: Bearer $this->access_token",
                  "Content-Type: application/json")
        );
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

        // For some reason we can't check the return value of curl_exec here.
        // It always comes back false, even if it succeeded.
        $result = curl_exec($ch);

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status != "204") {
            if ($this->debug) {
                Log::debug("sf->objUpdate: status $status, curl_exec returned $result");
            }
            return false;
        }

        /*
        * Returning TRUE below is a lie.  It turns out that you almost always get back
        * 204 from Salesforce, even if the update failed.  If it failed, you get
        * something like this in $result:
        {"message":"Unable to create/update fields: CH_UID__c. Please check the security settings of this field and verify that it is read/write for your profile or permission set.","errorCode":"INVALID_FIELD_FOR_INSERT_UPDATE","fields":["CH_UID__c"]}
        * XXX TODO: Fix this.
        */

        return true;
    }

    /*
    * Query an object.
    * Return decoded JSON object or FALSE on failure.
    */
    public function objQuery($objname, $objid)
    {
        $url = $this->instanceurl
                . "/services/data/v32.0/sobjects/$objname/$objid";

        $ch = curl_init();
        $this->setCommonCurlOptions($ch);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array("Authorization: Bearer $this->access_token")
        );
        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result == false) {
            $this->errorMessage = "curl_exec failed: " . $error;
            if ($this->debug) {
                Log::debug("sf->objQuery: curl_exec failed: $this->errorMessage");
            }
            return false;
        }

        $r = json_decode($result);
        if ($r == null) {
            $this->errorMessage = "json_decode failed: " .
                json_last_error_message();
            return false;
        }
        return $r;
    }

    private function setCommonCurlOptions($ch)
    {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connect_timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->response_timeout);
    }
}

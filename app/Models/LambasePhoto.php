<?php

/*
 * Lambase Photo
 */

namespace App\Models;

use GuzzleHttp;
use GuzzleHttp\Exception\RequestException;

class LambasePhoto
{
    public $person;

    public function __construct($person)
    {
        $this->person = $person;
    }

    public static function retrieveAllStatuses()
    {
        $contents = self::fetchUrl(setting('LambaseReportUrl') . "?method=photostatus_rpt", 60);
        /*
         * The above URL doesn't really return proper JSON so we
         * do some unspeakable things to it.
         */

        // strip out the quotes
        $contents = substr($contents, 1);
        $contents = substr($contents, 0, strlen($contents) - 1);
        // remove the backslashes because the quotes above
        $contents = str_replace('\\', '', $contents);
        // and split the records. (seriously?!?)
        $records = explode(";", $contents);

        $rows = [];
        $errors = [];
        foreach ($records as $data) {
            $row = json_decode($data);
            if ($row == null) {
                $errors[] = (object) [
                    'error'     => 'invalid json',
                    'data'      => $data,
                ];
            } else {
                $rows[] = (object) [
                     'person_id'    => $row->wsid,
                     'status'       => self::statusToCode($row->status, true),
                     'date'         => $row->date,
                ];
            }
        }
        return [ $rows, $errors ];
    }

    /*
     * Query lambase and get the status of a person's lam.
     * Returns an array of goodies, which we'll call $lamb:
     *    $lamb['error'] = TRUE if an error occurred trying to fetch.
     *    $lamb['message'] = User displayable message
     *    $lamb['data'] = TRUE if there is an image associated
     *    $lamb['image'] = remote file name of image
     *    $lamb['image_hash'] = md5 of image
     *    $lamb['status'] = lambase status code (see below)
     *  ... and some other stuff.
     *
     * The actual lambase status URL returns several things:
     *    wsid = ID (barcode)
     *    wshash = secret hash
     *  error = ???
     *  file = filename of image
     *  imghash = md5 of image
     *  status =
     *        -2    Rejected by laminate/ticketing/box office
     *        -1    Rejected by Rangers
     *        0    Submitted, pending review
     *        1    Approved by Rangers, awaiting laminate/ticketing/box office approval
     *        2    Approved by Laminate (all done!)
     *    data = whether or not there is an image on file
     *        1    image on file
     *        false    no image on file
     * Example:
     *
     * [wsid] => 46
     * [wshash] => 845B87A3DD5CB8BA8C6185EDA04AE808
     * [error] =>
     * [file] => 46_1.jpg
     * [imghash] => CA8EFFF84E7446CEC0834F5A74768204
     * [status] => 0
     * [data] => 1
     */

    public function getStatus()
    {
        $url = $this->buildStatusUrl();
        //echo $url;

        $json = $this->fetchUrl($url);
        if ($json == false) {
            return [
                'error'  => true,
                'message' => "Couldn't connect to lambase.",
            ];
        }

        $response = json_decode($json);

        if ($response == null) {
            return [
                'error'  => true,
                'message' =>  "Couldn't decode response from lambase.",
            ];
        }

        $result = [
            'error'      => false,
            'image'      => $response->{'file'},
            'image_hash' => $response->{'imghash'},
            'data'       => $response->{'data'},
            'status'     => $response->{'status'},
            'message'    => self::statusDecode($response->{'status'}, $response->{'data'}),
        ];

        if (isset($response->{'date'})) {
            $result['date'] = $response->{'date'};
        }

        return $result;
    }

    /*
     * Return the URL to check the status of a given person's
     * lam, complete with secret hash.
     */

    public function buildStatusUrl()
    {
        $person = $this->person;

        $url = setting('LambaseStatusUrl');

        if (empty($url)) {
            throw new \RuntimeException('LambaseStatusUrl is not set');
        }

        $query = http_build_query([
            'method'  => 'photostatus',
            'wsid'    => $person->id,
            'wshash'  => md5("fuckoff".$person->id),
            'mail'    => $person->email,
            'handle'  => $person->callsign,
            'barcode' => ''
        ]);

        return $url.'?'.$query;
    }

    /*
     * Return the contents of a page at a given URL.
     */

    public static function fetchUrl($url, $timeout = 60)
    {
        $client = new GuzzleHttp\Client();

        try {
            $res = $client->request('GET', $url, [
                'read_timeout' => $timeout,
                'connect_timeout' => 10
            ]);
        } catch (RequestException $e) {
            return null;
        }

        $status = $res->getStatusCode();
        if ($status != 200) {
            return null;
        }

        return $res->getBody();
    }

    /*
     * Check to see if we need to download the full image from
     * lambase.  This is true if either: (1) we don't have a
     * pic for this ID, or (2) if the md5 on our local copy
     * differs from the one on lambase.
     */

    public function downloadNeeded($lambaseMd5)
    {
        $localPic = Photo::localPathForPerson($this->person->id);

        if (!file_exists($localPic)) {
            // File does not exist.
            return true;
        }

        /*
        * The following is necessary because the md5
        * that lambase sends us is, bizarrely, the md5
        * of a base64 encoded version of the binary image.
        */

        $ourMd5 = md5(base64_encode(file_get_contents($localPic)));
        if (strcasecmp($ourMd5, $lambaseMd5) != 0) {
            return true;
        }

        return false;
    }

    /*
     * Return the URL for downloading an image for a given
     * person.
     * XXX This should really have secret hash to prevent
     * somebody from stealing all our images.  Ice says
     * he will do this soon.
     */

    public function getImageUrl($lambaseImage)
    {
        return setting('LambaseImageUrl')."/".$this->getPathname($lambaseImage);
    }

    public function getPathname($lambaseImage)
    {
        return $this->person->id."/".$lambaseImage;
    }

    /*
     * Download a photo from lambase and store it in our local cache.
     * Returns TRUE on success, FALSE on error.
     */

    public function downloadImage($lambaseImage)
    {
        $localPic = Photo::localPathForPerson($this->person->id);
        $lambaseImageUrl = $this->getImageUrl($lambaseImage);

        $imageData  = $this->fetchUrl($lambaseImageUrl);
        if (empty($imageData)) {
            return false;
        }
        $result = file_put_contents($localPic, $imageData);
        return $result;
    }

    /*
     * Determine whether the user needs to upload a photo.
     * Return TRUE if so, FALSE if not.
     */

    public function needToUpload(&$message)
    {
        $lamb = $this->getStatus();

        if ($lamb['error']) {
            $message = "Error in communicating with lambase: " . $lamb['message'];
            return false;
        }
        if (!$lamb['data']) {
            $message = "No photo on file with lambase";
            return true;
        }

        $message = $lamb['message'];
        if ($lamb['status'] < 0) {
            return true;
        }
        return false;
    }
    /*
     * Decode a lambase status code into a human-readable message.
     * Note this combines two things: (1) whether or not there is
     * an image at all, and (2) the status code.
     *
     * 7/2/2014: Ice talked to the BMORG Credentialing people and,
     * for 2014, they have decided to allow both Rangers and Gate to
     * do their own lam approvals.  As such, the codes -2 and +2 should
     * never get returned.  Next year, however, they may go back to
     * wanting to approve our lam photos.  So, we're keeping the codes
     * -2 and +2 but just changing the messages to be more assertive.
     */


    public static function statusDecode($status, $data)
    {
        static $lambaseStatusCodes = [
            "-2" => "Rejected by Lam team / Box Office",
            //        "-1" => "Rejected by Rangers",
            "-1" => "Rejected",
            "0" => "Submitted, pending approval",
            //        "1" => "Approved by Rangers, pending approval by Lam team / Box Office",
            "1" => "Approved",
            "2" => "Approved"
        ];

        if (!$data) {
            return "No lambase photo on file";
        }

        if (!isset($lambaseStatusCodes[$status])) {
            return "Unknown lambase status: $status";
        }

        return $lambaseStatusCodes[$status];
    }

    public static function statusToCode($status, $data)
    {
        if (!$data) {
            return 'missing';
        }

        $status = intval($status);
        if ($status < 0) {
            return 'rejected';
        } elseif ($status == 0) {
            return 'submitted';
        } else {
            return 'approved';
        }
    }

    public function getUploadUrl()
    {
        $person = $this->person;

        $query = http_build_query([
            'user'   => $person->id,
            'handle' => $person->callsign,
            'mail'   => $person->email,
            'hash'   => md5("fuckoff".$person->id),
            'barcode' => '',
        ]);
        return setting('LambaseJumpinUrl') . "?$query";
    }

    /*
     * We cache a local copy of whatever photo lambase has on file for us.
     * An edge case is if somebody deletes their photo on lambase.
     * This can result in a situation where lambase says the user has no
     * photo, but we have an image in our local cache.  In this case, we
     * delete the local copy, because lambase is the master at all times.
     */

    public function deleteLocal()
    {
        $file = Photo::localPathForPerson($this->person->id);

        if (file_exists($file)) {
            unlink($file);
        }
    }
}

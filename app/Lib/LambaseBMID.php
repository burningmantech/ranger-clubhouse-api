<?php

namespace App\Lib;

use Illuminate\Support\Facades\Auth;

use App\Models\ActionLog;
use App\Models\BMID;

class LambaseBMIDException extends \Exception
{
    public $lambaseResult;

    public function __construct($message, $result=null)
    {
        parent::__construct($message);
        $this->lambaseResult = $result;
    }
};

class LambaseBMID
{
    const TIMEOUT = 120;

    const DEBUG = 0;

    public static function upload($bmids)
    {
        $records = [];
        foreach ($bmids as $bmid) {
            $record = [
                'user'        => $bmid->person_id,
                'callsign'    => $bmid->person->callsign,
                'email'       => $bmid->person->email,
                'firstname'   => $bmid->person->first_name,
                'lastname'    => $bmid->person->last_name,
                'bpguid'      => $bmid->person->bpguid,
                'meals'       => $bmid->meals,
                'showers'     => ($bmid->showers ? "Y" : "N"),
                'mvr'         => ($bmid->org_vehicle_insurance ? "Y" : "N"),
                'title1'      => $bmid->title1,
                'title2'      => $bmid->title2,
                'title3'      => $bmid->title3,
                'batchid'     => $bmid->batch,
                'printstatus' => "readytoprint",
            ];

            switch ($bmid->status) {
            case 'ready_to_reprint_lost':
                $record['printstatus'] = "readytoreplace";
                break;

            case 'ready_to_reprint_changed':
                $record['printstatus'] ="readytoreprint";
                break;
            }
            //$record['printstatus'] = "testing";
            $records[] = $record;
        }

        if (self::DEBUG) {
            foreach ($bmids as $bmid) {
                $bmid->uploadedToLambase = 1;
            }
        } else {
            $json = json_encode($records, JSON_UNESCAPED_SLASHES);
            $hash = urlencode(md5("fuckoff" . $json));


            $url = setting('LambasePrintStatusUpdateUrl') . "?method=printstatus";
            $url = $url . "&hash=$hash";

            ActionLog::record(Auth::user(), 'lambase-submit', 'Lambase submission', [
                'json'    => $json,
                'records' => $records,
                'url'     => $url
             ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_HTTPHEADER,
                [ 'Content-Type: application/json', 'Content-Length: ' . strlen($json) ]
            );
            $result = curl_exec($ch);

            if ($result === false) {
                $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                throw new LambaseBMIDException("Lambase Unknown HTTP response code [$httpCode] or error [$error]");
            }

            curl_close($ch);

            ActionLog::record(Auth::user(), 'lambase-response', 'Lambase response', [ 'result' => $result ]);

            $decodedResult = json_decode($result);
            if ($decodedResult == null) {
                throw new LambaseBMIDException("Failed to decode Lambase", $result);
            }

            $success = [];
            foreach ($decodedResult as $n => $obj) {
                $success[$obj->wsid] = true;
            }

            foreach ($bmids as $bmid) {
                $bmid->uploadedToLambase = isset($success[$bmid->person_id]);
            }
        }
    }
};

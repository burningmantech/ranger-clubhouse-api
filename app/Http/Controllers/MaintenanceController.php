<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;

use App\Models\ActionLog;
use App\Models\Broadcast;
use App\Models\ErrorLog;
use App\Models\LambasePhoto;
use App\Models\Person;
use App\Models\PersonPhoto;
use App\Models\Photo;

use App\Mail\DailyReportMail;

class MaintenanceController extends ApiController
{
    public function photoSync() {
        ini_set('max_execution_time', 300);

        $this->checkMaintenanceToken();

        list ($photos, $errors) = LambasePhoto::retrieveAllStatuses();
        $results = [];

        foreach ($photos as $photo) {
            if (!$photo->person_id) {
                continue;
            }

            $pm = PersonPhoto::find($photo->person_id);
            // A lack of PersonPhoto record OR change in status or approval date
            // reconstruct the record
            // TODO: Ask Ice about adding a image url column to the retrieve all request
            if (!$pm
                || $pm->status != $photo->status
                || (string) $pm->lambase_date != $photo->date) {
                $person = Person::find($photo->person_id);

                if ($person) {
                    $results[] = Photo::retrieveInfo($person, true);
                }
            }
        }

        return response()->json([ 'results' => $results, 'errors' => $errors ]);
    }

    public function dailyReport()
    {
        $this->checkMaintenanceToken();

        $failedBroadcasts = Broadcast::findLogs([ 'lastday' => true, 'failed' => true]);
        $errorLogs = ErrorLog::findForQuery([ 'lastday' => true, 'page_size' => 1000 ])['logs'];

        $roleLogs = ActionLog::findForQuery([ 'lastday' => 'true', 'page_size' => 1000, 'events' => [ 'person-role-add', 'person-role-remove' ] ], false)['logs'];
        $statusLogs = ActionLog::findForQuery([ 'lastday' => 'true', 'page_size' => 1000, 'events' => [ 'person-status-change', 'person-status-change' ] ], false)['logs'];
        foreach ($statusLogs as $log) {
            $json = json_decode($log->data);
            $log->oldStatus = $json->status[0];
            $log->newStatus = $json->status[1];
        }

        mail_to('youngfrankenstein@burningman.org', new DailyReportMail($failedBroadcasts, $errorLogs, $roleLogs, $statusLogs));
        return $this->success();
    }


    private function checkMaintenanceToken()
    {
        $token = setting('MaintenanceToken');

        if (empty($token)) {
            throw new \RuntimeException("Maintenance token not set -- cannot authorzie request");
        }

        $ourToken = request()->input('token');

        if ($token != $ourToken) {
            $this->notPermitted("Not authorized.");
        }
    }
}

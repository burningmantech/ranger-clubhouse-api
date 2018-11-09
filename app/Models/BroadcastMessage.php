<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ApiModel;

class BroadcastMessage extends ApiModel
{
    protected $table = "broadcast_message";

    // Allow mass assignment
    protected $guarded = [];

    /*
     * Record an inbound or outbound message which might be associated
     * with a person and/or broadcast.
     *
     * @param int $broadcastId broadcast identifier
     * @param string $status message status (Broadcast::STATUS_*)
     * @param int  $personId person identifier
     * @param string $type message type (sms, email, Clubhouse)
     * @param string $address email or sms address
     * @param string $direction outbound (sent by Clubhouse), or inbound (received by Clubhouse)
     * @param string $message (optional) incoming message from phone
     * @param return the id of the newly created broadcast_message row
     */

    public static function log($broadcastId, $status, $personId, $type, $address, $direction, $message=null)
    {
        $columns = [
            'direction'    => $direction,
            'status'       => $status,
            'address_type' => $type,
            'address'      => $address
        ];

        if ($broadcastId) {
            $columns['broadcast_id'] = $broadcastId;
        }

        if ($personId) {
            $columns['person_id'] = $personId;
        }

        if ($message) {
            $columns['message'] = $message;
        }

        $log = new BroadcastMessage($columns);
        $log->saveOrFail();

        return $log->id;
    }

}

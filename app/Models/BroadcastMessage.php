<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastMessage extends ApiModel
{
    protected $table = "broadcast_message";

    // Allow mass assignment - the table is not exposed directly through an API
    protected $guarded = [];

    const int DEFAULT_PAGE_SIZE = 50;

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Find all unknown phone numbers (aka spam) for this year.
     *
     * @param $year
     * @return Collection
     */

    public static function findUnknownPhonesForYear($year): Collection
    {
        return self::where('status', Broadcast::STATUS_UNKNOWN_PHONE)
            ->whereYear('created_at', $year)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Retrieve all the unknown phone numbers for the last 24 hours.
     *
     * @return Collection
     */

    public static function retrieveUnknownPhonesForDailyReport(): Collection
    {
        return self::where('status', Broadcast::STATUS_UNKNOWN_PHONE)
            ->where('created_at', '>=', now()->subHours(24))
            ->get();
    }

    /**
     * Find the broadcasts based on the given query
     *
     * @param $query
     * @return array
     */

    public static function findForQuery($query): array
    {
        $page = (int)($query['page'] ?? 1);
        $pageSize = (int)($query['page_size'] ?? self::DEFAULT_PAGE_SIZE);
        $status = $query['status'] ?? null;
        $year = $query['year'] ?? null;
        $personId = $query['person_id'] ?? null;
        $direction = !empty($query['direction']) ? $query['direction'] : null;
        $isAsc = ($query['order'] ?? '') == 'asc';

        $sql = self::query();

        if ($year) {
            $sql->whereYear('created_at', $year);
        }

        if ($personId) {
            $sql->where('person_id', $personId);
        }

        if ($status) {
            $sql->whereIn('status', $status);
        }

        if ($direction) {
            $sql->where('direction', $direction);
        }

        if ($status) {
            $sql->where('status', $status);
        }

        $total = $sql->count();

        // Figure out pagination
        $page = $page - 1;
        if ($page < 0) {
            $page = 0;
        }
        if ($pageSize <= 0) {
            $pageSize = self::DEFAULT_PAGE_SIZE;
        }

        $sql->offset($page * $pageSize)->limit($pageSize);
        $sql->with(['person:id,callsign'])
            ->orderBy('created_at', $isAsc ? 'asc' : 'desc');

        $rows = $sql->get();

        return [
            'messages' => $rows,
            'total' => $total,
            'total_pages' => (int)(($total + ($pageSize - 1)) / $pageSize),
            'page_size' => $pageSize,
            'page' => $page + 1,
        ];
    }

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

    public static function record($broadcastId, $status, $personId, $type, $address, $direction, $message = null)
    {
        $columns = [
            'direction' => $direction,
            'status' => $status,
            'address_type' => $type,
            'address' => $address
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

        $log = BroadcastMessage::create($columns);

        return $log->id;
    }

    public static function countFail($broadcastId, $addressType)
    {
        return self::where('broadcast_id', $broadcastId)
            ->where('address_type', $addressType)
            ->where('status', Broadcast::STATUS_SERVICE_FAIL)
            ->count();
    }

}

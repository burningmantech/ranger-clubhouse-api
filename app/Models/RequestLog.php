<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestLog extends ApiModel
{
    protected $table = 'request_log';

    protected $guarded = [];

    protected bool $timestamp = false;

    const int EXPIRE_DAYS_DEFAULT = 7;
    const int PAGE_SIZE_DEFAULT = 50;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'response_payload' => 'json',
            'request_payload' => 'json',
        ];
    }

    // created_at is handled by the database itself
    public $timestamps = false;

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public static function findForQuery($query): array
    {
        $personId = $query['person_id'] ?? null;
        $page = (int)($query['page'] ?? 1);
        $pageSize = (int)($query['page_size'] ?? self::PAGE_SIZE_DEFAULT);
        $sort = $query['sort'] ?? 'desc';
        $startTime = $query['start_time'] ?? null;
        $endTime = $query['end_time'] ?? null;

        $sql = self::query();

        if ($personId) {
            $sql->where('person_id', $personId);
        }

        if ($startTime) {
            $sql->where('created_at', '>=', $startTime);
        }

        if ($endTime) {
            $sql->where('created_at', '<=', $endTime);
        }

        $total = $sql->count();

        if (!$total) {
            // Nada.. don't bother
            return [
                'request_log' => [],
                'meta' => ['page' => 0, 'total' => 0, 'total_pages' => 0]
            ];
        }

        // Results sort 'asc' or 'desc'
        $sql->orderBy('created_at', ($sort == 'asc' ? 'asc' : 'desc'));

        // Figure out pagination
        $page = $page - 1;
        if ($page < 0) {
            $page = 0;
        }

        if ($pageSize <= 0) {
            $pageSize = self::PAGE_SIZE_DEFAULT;
        }

        $sql->offset($page * $pageSize)->limit($pageSize);

        // .. and go get it!
        $rows = $sql->with('person:id,callsign')->get();

        return [
            'request_log' => $rows,
            'meta' => [
                'total' => $total,
                'total_pages' => (int)(($total + ($pageSize - 1)) / $pageSize),
                'page_size' => $pageSize,
                'page' => $page + 1,
            ]
        ];

    }

    /**
     * Record an API request
     *
     * @param int|null $personId person who requested
     * @param string $ips
     * @param string $url the api url
     * @param int $status response status
     * @param string $method
     * @param int $responseSize response content size
     * @param float $completionTime completion time in milliseconds
     * @param mixed $requestPayload
     * @param mixed $responsePayload
     */

    public static function record(?int    $personId,
                                  string  $ips,
                                  string  $url,
                                  int     $status,
                                  string  $method,
                                  int     $responseSize,
                                  float   $completionTime,
                                  mixed $requestPayload,
                                  mixed $responsePayload): void
    {
        self::create([
            'person_id' => $personId,
            'ips' => $ips,
            'url' => $url,
            'status' => $status,
            'method' => $method,
            'response_size' => $responseSize,
            'response_payload' => $responsePayload,
            'request_payload' => $requestPayload,
            'completion_time' => round($completionTime, 2),
        ]);
    }

    public static function expire(int $days = self::EXPIRE_DAYS_DEFAULT): void
    {
        self::where('created_at', '<=', now()->subDays($days))->delete();
    }
}
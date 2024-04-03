<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ErrorLog extends ApiModel
{
    const PAGE_SIZE_DEFAULT = 50;

    // Allow mass assignment.
    protected $guarded = [];

    /* protected $casts = [
         'data' => 'array'
     ];*/

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Record an error
     * @param string $error_type
     * @param array $data
     */

    public static function record(string $error_type, array $data = []): void
    {
        $error = [
            'error_type' => $error_type,
        ];

        $req = request();
        if ($req) {
            $data['method'] = $req->method();
            $data['parameters'] = $req->all();

            $error['ip'] = $req->getClientIp();
            $error['user_agent'] = $req->userAgent();
            $error['url'] = $req->fullUrl();
        }

        $error['data'] = json_encode($data);

        self::create($error);
    }

    /**
     * Record a PHP exception
     *
     * @param Throwable $e the exception which occurred
     * @param string $error_type the type to record
     * @param array $extra any additional data to be logged
     */

    public static function recordException(Throwable $e, string $error_type, array $extra = []): void
    {
        // Inspect the exception for name, message, source location,
        // and backtrace

        $data = [];
        if ($e instanceof Exception) {
            $data['exception'] = [
                'class' => class_basename($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }

        // Record the method and parameters
        $req = request();
        if ($req) {
            $data['method'] = $req->method();
            $data['url'] = $req->fullUrl();
            $data['parameters'] = $req->all();
        }

        $data = array_merge($data, $extra);

        $error = [
            'error_type' => $error_type,
            'data' => json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE),
        ];

        // Include the IP, user_agent and URL location
        if ($req) {
            $error['ip'] = $req->getClientIp();
            $error['user_agent'] = $req->userAgent();
            $error['url'] = $req->fullUrl();
        }

        // Who is the user?
        if (Auth::check()) {
            $error['person_id'] = Auth::id();
        }

        try {
            self::create($error);
        } catch (QueryException $e) {
            //Log::emergency("error log create exception ".$e->getMessage());
            //error_log('ERROR LOG '.$e->getTraceAsString());
        }
    }

    /**
     * Find error log based on a criteria
     *
     * @param array $query
     *
     * person_id - person to find
     * error_type - specific error
     * start_at - error record on or after datetime
     * ends_at - error record on or before datetime
     * sort - 'asc' or 'desc'
     * page - page offset (1 to n)
     * page_size - size of page
     */

    public static function findForQuery(array $query): array
    {
        $sql = self::query();

        // Specific person to find
        if (isset($query['person_id'])) {
            $sql = $sql->where('person_id', $query['person_id']);
        }

        // Component to find
        if (isset($query['error_type'])) {
            $sql = $sql->where('error_type', $query['error_type']);
        }

        // logged on or after a specific datetime
        if (isset($query['start_at'])) {
            $sql = $sql->where('created_at', '>=', $query['start_at']);
        }

        // logged on or before a specific datetime
        if (isset($query['ends_at'])) {
            $sql = $sql->where('created_at', '<=', $query['ends_at']);
        }

        if (isset($query['last_day'])) {
            $sql->whereRaw('created_at >= DATE_SUB(?, INTERVAL 1 DAY)', [now()]);
        }

        // How many total for the query
        $total = $sql->count();

        if (!$total) {
            // Nada.. don't bother
            return ['error_logs' => [], 'meta' => ['page' => 0, 'total' => 0, 'total_pages' => 0]];
        }

        // Results sort 'asc' or 'desc'
        if (isset($query['sort'])) {
            $sql = $sql->orderBy('created_at', ($query['sort'] == 'asc' ? 'asc' : 'desc'));
        } else {
            $sql = $sql->orderBy('created_at', 'desc');
        }

        // Figure out pagination
        $pageSize = (int)($query['page_size'] ?? self::PAGE_SIZE_DEFAULT);
        $page = (int)($query['page'] ?? 1);
        $page = $page - 1;
        if ($page < 0) {
            $page = 0;
        }
        if ($pageSize <= 0) {
            $pageSize = self::PAGE_SIZE_DEFAULT;
        }

        $sql = $sql->offset($page * $pageSize)->limit($pageSize);

        // .. and go get it!
        $rows = $sql->with('person:id,callsign')->get();

        return [
            'error_logs' => $rows,
            'meta' => [
                'total' => $total,
                'total_pages' => (int)(($total + ($pageSize - 1)) / $pageSize),
                'page_size' => $pageSize,
                'page' => $page + 1,
            ]
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;

class ErrorLog extends ApiModel
{
    const PAGE_SIZE_DEFAULT = 50;

    // Allow mass assignment.
    protected $guarded = [];

   /* protected $casts = [
        'data' => 'array'
    ];*/

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    /*
     * Record an error
     */

    public static function record($error_type, $data = [])
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

        $error['data'] = $data;

        self::create($error);
    }

    /*
     * Record a PHP exception
     *
     * @param Exception $e the exception which occured
     * @param string $error_type the type to record
     * @param array $extra any additional data to be logged
     */

    public static function recordException($e, $error_type, $extra = [])
    {
        // Inspect the exception for name, message, source location,
        // and backtrace
        $data = [
            'exception' => [
                'class' => class_basename($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]
        ];

        // Record the method and parameters
        $req = request();
        if ($req) {
            $data['method'] = $req->method();
            $data['url'] = $req->fullUrl();
        }

        $data = array_merge($data, $extra);

        $error = [
            'error_type' => $error_type,
            'data' => json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_UNICODE),
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

    /*
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

    public static function findForQuery($query)
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
        $pageSize = $query['page_size'] ?? self::PAGE_SIZE_DEFAULT;
        if (isset($query['page'])) {
            $page = $query['page'] - 1;
            if ($page < 0) {
                $page = 0;
            }
        } else {
            $page = 0;
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

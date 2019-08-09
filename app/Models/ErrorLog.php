<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Person;
use App\Models\ApiModel;

use Illuminate\Support\Facades\Auth;

class ErrorLog extends ApiModel
{
    const PAGE_SIZE_DEFAULT = 50;

    // Allow mass assignment.
    protected $guarded = [];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    /*
     * Record an error
     */

    public static function record($error_type, $data=[])
    {
        $error = [
            'error_type' => $error_type,
        ];

        $req = request();
        if ($req) {
            $data['method']      = $req->method();
            $data['parameters']  = $req->all();

            $error['ip']         = $req->ip();
            $error['user_agent'] = $req->userAgent();
            $error['url']        = $req->fullUrl();
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

    public static function recordException($e, $error_type, $extra=[])
    {
        // Inspect the exception for name, message, source location,
        // and backtrace
        $data = [
            'exception' => [
                'class'   => class_basename($e),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'backtrace'  => $e->getTrace(),
            ]
        ];

        // Record the method and parameters
        $req = request();
        if ($req) {
            $data['method'] = $req->method();
            $data['parameters'] = $req->all();
        }

        $data = array_merge($data, $extra);

        $error = [
            'error_type' => $error_type,
            'data'       => $data
        ];

        // Include the IP, user_agent and URL location
        if ($req) {
            $error['ip'] = $req->ip();
            $error['user_agent'] = $req->userAgent();
            $error['url'] = $req->fullUrl();
        }

        // Who is the user?
        if (Auth::check()) {
            $error['person_id'] = Auth::user()->id;
        }

        self::create($error);
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
            $sql->where('created_at', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL 25 HOUR)'));
        }

        // How many total for the query
        $total = $sql->count();

        if (!$total) {
            // Nada.. don't bother
            return [ 'logs' => [ ], 'page' => 0, 'total' => 0, 'total_pages' => 0 ];
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
            'logs'        => $rows,
            'total'       => $total,
            'total_pages' => (int) (($total + ($pageSize - 1))/$pageSize),
            'page_size'   => $pageSize,
            'page'        => $page + 1,
         ];
    }

    /*
     * Encode the data column as JSON if its an array.
     */

    public function setDataAttribute($value)
    {
        $this->attributes['data'] = is_array($value) ? json_encode($value) : $value;
    }
}

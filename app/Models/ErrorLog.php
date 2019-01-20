<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Person;
use App\Models\ApiModel;

class ErrorLog extends ApiModel
{
    const PAGE_SIZE_DEFAULT = 50;

    // Allow mass assignment.
    protected $guarded = [];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public static function findForQuery($query)
    {
        $sql = self::query();

        // Specific person to find
        if (isset($query['person_id'])) {
            $sql = $sql->where('person_id', $query['person_id']);
        }

        // Component to find
        if (isset($query['component'])) {
            $sql = $sql->where('component', $query['component']);
        }

        // logged on or after a specific datetime
        if (isset($query['start_at'])) {
            $sql = $sql->where('created_at', '>=', $query['start_at']);
        }

        // logged on or before a specific datetime
        if (isset($query['ends_at'])) {
            $sql = $sql->where('created_at', '<=', $query['ends_at']);
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
}

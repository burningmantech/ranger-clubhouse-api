<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\ErrorLog;

class ErrorLogController extends ApiController
{
    /**
     * Record an error trapped by the client
     *
     * Does not require authorization.
     */
    public function record()
    {
        $data = request()->input('data');
        $personId = request()->input('person_id');

        $record = [
            'component'  => 'client',
            'ip'         => request()->ip(),
            'user_agent' => request()->userAgent(),
            'data'       => $data,
        ];

        if ($personId) {
            $record['person_id'] = $personId;
        }

        $log = new ErrorLog($record);
        $log->save();

        return response('success', 200);
    }

    /**
     * Retrieve the error log
     */

     public function index()
     {
         $this->authorize('index', ErrorLog::class);

         $params = request()->validate([
             'person_id' => 'sometimes|integer',
             'sort'      => 'sometimes|string',

             'starts_at'    => 'sometimes|datetime',
             'ends_at'   => 'sometimes|datetime',

             'component' => 'sometimes|string',

             'page'      => 'sometimes|integer',
             'page_size' => 'sometimes|integer',
        ]);

        return response()->json(ErrorLog::findForQuery($params));
    }
}

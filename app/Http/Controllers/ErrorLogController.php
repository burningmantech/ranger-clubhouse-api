<?php

namespace App\Http\Controllers;

use App\Models\ErrorLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ErrorLogController extends ApiController
{
    /**
     * Record an error trapped by the client
     *
     * Does not require authorization.
     *
     * @return JsonResponse
     * @throws ValidationException
     */

    public function record(): JsonResponse
    {
        $data = request()->input('data');
        $url = request()->input('url');
        $personId = request()->input('person_id');
        $errorType = request()->input('error_type');

        $record = [
            'error_type' => $errorType,
            'ip' => request_ip(),
            'url' => $url,
            'user_agent' => request()->userAgent(),
            'data' => $data,
        ];

        if (is_numeric($personId)) {
            $record['person_id'] = $personId;
        }

        $log = new ErrorLog($record);
        $log->save();

        return $this->success();
    }

    /**
     * Retrieve the error log
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $this->authorize('index', ErrorLog::class);

        $params = request()->validate([
            'person_id' => 'sometimes|integer',
            'sort' => 'sometimes|string',

            'starts_at' => 'sometimes|date',
            'ends_at' => 'sometimes|date',

            'error_type' => 'sometimes|string',

            'page' => 'sometimes|integer',
            'page_size' => 'sometimes|integer',
        ]);

        $result = ErrorLog::findForQuery($params);

        return $this->success($result['error_logs'], $result['meta'], 'error_logs');
    }

    /**
     * Purge the error log
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function purge(): JsonResponse
    {
        $this->authorize('purge', ErrorLog::class);

        ErrorLog::query()->truncate();

        return response()->json(['status' => 'success']);
    }
}

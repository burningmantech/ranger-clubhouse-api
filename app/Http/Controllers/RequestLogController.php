<?php

namespace App\Http\Controllers;

use App\Models\RequestLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class RequestLogController extends ApiController
{
    /**
     * Retrieve the request log
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $this->authorize('index', RequestLog::class);

        $params = request()->validate([
            'sort' => 'sometimes|string',

            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date',

            'page' => 'sometimes|integer',
            'page_size' => 'sometimes|integer',

            'person_id' => 'sometimes|integer',
        ]);

        $result = RequestLog::findForQuery($params);

        return $this->success($result['request_log'], $result['meta'], 'request_log');
    }

    /**
     * Expire the request log
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function expire(): JsonResponse
    {
        $this->authorize('expire', RequestLog::class);

        $params = request()->validate([
            'days' => 'sometimes|integer|gt:0',
        ]);

        RequestLog::expire($params['days'] ?? RequestLog::EXPIRE_DAYS_DEFAULT);

        return $this->success();
    }
}

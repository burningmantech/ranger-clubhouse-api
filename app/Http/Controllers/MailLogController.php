<?php

namespace App\Http\Controllers;

use App\Models\MailLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Psy\Util\Json;

class MailLogController extends ApiController
{
    /**
     * Retrieve the mail log
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $this->authorize('index', MailLog::class);

        $query = request()->validate([
            'person_id' => 'sometimes|integer',
            'year' => 'sometimes|digits:4',
            'page' => 'sometimes|integer',
            'page_size' => 'sometimes|integer'
        ]);

        $result = MailLog::findForQuery($query);
        return $this->success($result['mail_log'], $result['meta'], 'mail_log');
    }

    /**
     * Retrieve the stats for either the person or website
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function stats(): JsonResponse
    {
        $this->authorize('stats', MailLog::class);

        $query = request()->validate(['person_id' => 'sometimes|integer']);
        $personId = $query['person_id'] ?? null;

        return response()->json([
            'years' => MailLog::retrieveYears($personId),
            'counts' => MailLog::retrieveStats($personId),
        ]);
    }
}

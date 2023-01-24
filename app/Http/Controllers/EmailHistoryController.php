<?php

namespace App\Http\Controllers;

use App\Models\EmailHistory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class EmailHistoryController extends ApiController
{
    /**
     * Show a list of email history records
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $this->authorize('index', EmailHistory::class);

        $params = request()->validate([
            'person_id' => 'required_without:email|integer|exists:person,id',
            'email' => 'required_without:person_id|string',
            'include_source_person' => 'sometimes|boolean',
        ]);

        return $this->success(EmailHistory::findForQuery($params), null, 'email_history');
    }
}

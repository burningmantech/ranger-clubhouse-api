<?php

namespace App\Http\Controllers;

use App\Lib\BulkUploader;
use App\Models\Certification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class BulkUploadController extends ApiController
{
    /**
     * Send back a list of available bulk uploader actions. Certifications are dynamically added.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function actions(): JsonResponse
    {
        $this->authorize('isAdmin');

        $actions = BulkUploader::ACTION_DESCRIPTIONS;

        $certifications = Certification::orderBy('title')->get();
        $certActions = [];
        foreach ($certifications as $cert) {
            $certActions[] = [
                'id' => "cert-{$cert->id}",
                'label' => "Record/Update {$cert->title} cert.",
                'help' => BulkUploader::HELP_CERTIFICATION
            ];
        }

        $actions[] = [
            'label' => 'Certification Actions',
            'options' => $certActions
        ];

        return response()->json([ 'actions' =>  $actions ]);
    }

    /**
     * Process bulk uploader actions
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function process(): JsonResponse
    {
        $params = request()->validate([
            'action' => 'required|string',
            'records' => 'required|string',
            'commit' => 'sometimes|boolean',
            'reason' => 'sometimes|string',
        ]);

        $this->authorize('isAdmin');

        $action = $params['action'];
        $commit = $params['commit'] ?? false;
        $reason = $params['reason'] ?? 'bulk upload';
        $recordsParam = $params['records'];

        return response()->json([
            'results' => BulkUploader::process($action, $commit, $reason, $recordsParam),
            'commit' => (bool)$commit
        ]);
    }
}

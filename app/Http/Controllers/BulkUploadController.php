<?php

namespace App\Http\Controllers;

use App\Lib\BulkUploader;

class BulkUploadController extends ApiController
{
    public function update()
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
            'commit' => $commit ? true : false
        ]);
    }
}

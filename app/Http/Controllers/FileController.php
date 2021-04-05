<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileController extends ApiController
{
    const TYPES = [
        'zip' => 'application/zip',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'txt' => 'text/plain',
        'csv' => 'text/csv'
    ];

    /**
     * Serve up a file from storage - ONLY FOR DEVELOPMENT.
     *
     * @param $file
     * @return BinaryFileResponse
     * @throws AuthorizationException
     */

    public function serve($file): BinaryFileResponse
    {
        if (!app()->isLocal()) {
            $this->notPermitted("Unauthorized.");
        }

        $path = storage_path($file);
        if (!file_exists($path)) {
            abort(404);
        }
        $info = pathinfo($file);
        return response()->file($path,
            [
                'Content-Type' => self::TYPES[$info['extension']] ?? 'text/plain',
                'Access-Control-Allow-Origin' => '*'
            ]
        );

    }
}

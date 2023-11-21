<?php

namespace App\Http\Controllers;

use App\Models\AssetAttachment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AssetAttachmentController extends ApiController
{
    /**
     * retrieve asset attachments
     *
     * @return JsonResponse
     */

    public function index(): JsonResponse
    {
        return $this->success(AssetAttachment::findAll(), null, 'asset_attachment');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws ValidationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', AssetAttachment::class);

        $asset_attachment = new AssetAttachment;
        $this->fromRest($asset_attachment);

        if ($asset_attachment->save()) {
            return $this->success($asset_attachment);
        }

        return $this->restError($asset_attachment);
    }

    /**
     * Return an attachment
     *
     * @param AssetAttachment $asset_attachment
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(AssetAttachment $asset_attachment): JsonResponse
    {
        $this->authorize('view', AssetAttachment::class);
        return $this->success($asset_attachment);
    }

    /**
     * Update an attachment
     *
     * @param AssetAttachment $asset_attachment
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws ValidationException
     */

    public function update(AssetAttachment $asset_attachment): JsonResponse
    {
        $this->authorize('update', $asset_attachment);
        $this->fromRest($asset_attachment);

        if ($asset_attachment->save()) {
            return $this->success($asset_attachment);
        }

        return $this->restError($asset_attachment);
    }

    /**
     * Delete an attachment
     * 
     * @param AssetAttachment $asset_attachment
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(AssetAttachment $asset_attachment): JsonResponse
    {
        $this->authorize('delete', $asset_attachment);
        $asset_attachment->delete();
        return $this->restDeleteSuccess();
    }
}

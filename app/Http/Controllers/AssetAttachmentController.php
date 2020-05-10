<?php

namespace App\Http\Controllers;

use App\Models\AssetAttachment;
use App\Http\Controllers\ApiController;

use Illuminate\Http\Request;

class AssetAttachmentController extends ApiController
{
    /*
     * retrieve asset attachments
     */
    public function index()
    {
        return $this->success(AssetAttachment::findAll(), null, 'asset_attachment');
    }

    /*
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('store', AssetAttachment::class);

        $asset = new AssetAttachment;
        $this->fromRest($asset);

        if ($asset->save()) {
            return $this->success($asset_attachment);
        }

        return $this->restError($asset_attachment);
    }

    /*
     * Display the specified resource.
     */
    public function show(AssetAttachment $asset_attachment)
    {
        $this->authorize('view', AssetAttachment::class);
        return $this->success($asset_attachment);
    }

    /*
     * Update the specified resource in storage.
     */

    public function update(Request $request, AssetAttachment $asset_attachment)
    {
        $this->authorize('update', $asset_attachment);
        $this->fromRest($asset_attachment);

        if ($asset->save()) {
            return $this->success($asset_attachment);
        }

        return $this->restError($asset_attachment);
    }

    /*
     * Remove the specified resource from storage.
     */
    public function destroy(AssetAttachment $asset_attachment)
    {
        $this->authorize('delete', $asset_attachment);
        $asset->delete();
        return $this->restDeleteSuccess();
    }
}

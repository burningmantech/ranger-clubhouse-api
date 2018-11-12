<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Http\Controllers\ApiController;

use Illuminate\Http\Request;

class AssetController extends ApiController
{
    /*
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->authorize('view', [Asset::class]);

        $query = request()->validate([
            'barcode'         => 'sometimes|string',
            'include_history' => 'sometimes|bool',
            'year'            => 'sometimes|integer',
            'limit'           => 'sometimes|integer',
            'offset'          => 'sometimes|integer',
        ]);

        $assets = Asset::findForQuery($query);
        return $this->success($assets, null, 'asset');
    }

    /*
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('store', Asset::class);

        $asset = new \App\Models\Asset;
        $this->fromRest($asset);

        if ($asset->save()) {
            return $this->success($asset);
        }

        return $this->restError($asset);
    }

    /*
     * Display the specified resource.
     */
    public function show(Asset $asset)
    {
        $this->authorize('view', Asset::class);
        return $this->success($asset);
    }

    /*
     * Update the specified resource in storage.
     */

    public function update(Request $request, Asset $asset)
    {
        $this->authorize('update', $asset);
        $this->fromRest($asset);

        if ($asset->save()) {
            return $this->success($asset);
        }

        return $this->restError($asset);
    }

    /*
     * Remove the specified resource from storage.
     */
    public function destroy(Asset $asset)
    {
        $this->authorize('delete', $asset);
        $asset->delete();
        $this->log('asset-delete', 'Asset Deleted', [ 'id' => $asset->id]);
        return $this->restDeleteSuccess();
    }
}

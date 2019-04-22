<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Helpers\SqlHelper;
use App\Http\Controllers\ApiController;

use App\Models\Asset;
use App\Models\AssetPerson;

class AssetPersonController extends ApiController
{
    /**
     * Find an asset set based on person and/or year.
     *
     */
    public function index()
    {
        $params = request()->validate([
            'person_id' => 'sometimes|integer',
            'year'      => 'sometimes|integer',
        ]);

        $rows = AssetPerson::findForQuery($params);

        return $this->success($rows, null, 'asset_person');
    }

    /**
     * Create an asset person
     */
    public function store(Request $request)
    {
        $this->authorize('store', [ AssetPerson::class ]);
        $asset_person = new AssetPerson;
        $this->fromReset($asset_person);

        if (!$this->save()) {
            return $this->restError($asset_person);
        }

        return $this->success($asset_person);
    }

    /**
     * Retrieve an asset person
     */
    public function show(AssetPerson $asset_person)
    {
        $this->authorize('show', $asset_person);
        $asset_person->loadRelationships();

        return $this->success($asset_person);
    }

    /**
     * Update an AssetPerson record
     */
    public function update(AssetPerson $asset_person)
    {
        $this->authorize('update', $asset_person);
        $this->fromRest($asset_person);
        if (!$asset_person->save()) {
            return $this->restError($asset_person);
        }

        $asset_person->loadRelationships();
        return $this->success($asset_person);
    }

    /**
     * Delete an asset person record
     */
    public function destroy(AssetPerson $asset_person)
    {
        $this->authorize('destroy', $asset_person);
        $asset_person->delete();

        return $this->restDeleteSuccess();
    }
}

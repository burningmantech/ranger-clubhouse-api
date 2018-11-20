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

    /**
     * Checkout a barcode
     */

     public function checkout()
     {
         $this->authorize('checkout', [ AssetPerson::class ]);

         $params = request()->validate([
             'barcode'       => 'required|string',
             'person_id'     => 'required|integer',
             'year'          => 'sometimes|integer',
             'attachment_id' => 'sometimes|integer|nullable',
         ]);

         $year = $params['year'] ?? date('Y');

         $asset = Asset::findByBarcodeYear($params['barcode'], $year);

         if ($asset == null) {
             return response()->json([ 'status' => 'not-found' ]);
         }

         $person = AssetPerson::findCheckedOutPerson($asset->id, $year);
         if ($person) {
             return response()->json([
                'status'    => 'checked-out',
                'person_id' => $person->id,
                'callsign'  => $person->callsign,
             ]);
         }

         $row = new AssetPerson([
             'asset_id'     => $asset->id,
             'attachment_id' => @$params['attachment_id'],
             'checked_out'  => SqlHelper::now(),
             'person_id'    => $params['person_id'],
         ]);

         if (!$row->save()) {
             error_log("Got here");
             throw new \InvalidArgumentException("Unknown error trying to create checkout record. ".$row->getErrors());
         }

         return response()->json([ 'status' => 'success' ]);
     }

     public function checkin(AssetPerson $asset_person)
     {
         $this->authorize('checkin', $asset_person);

         if ($asset_person->checked_in) {
             throw new \InvalidArgumentException("Asset is already checked in");
         }

         $asset_person->checked_in = SqlHelper::now();

         if (!$asset_person->save()) {
             throw new \InvalidArgumentException("Cannot check asset in");
         }

         return $this->success();
     }
}

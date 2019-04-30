<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Http\Controllers\ApiController;
use App\Models\AssetPerson;
use App\Helpers\SqlHelper;

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
        $this->authorize('index', Asset::class);

        $query = request()->validate([
            'barcode'         => 'sometimes|string',    // specific barcode to find
            'include_history' => 'sometimes|boolean',   // include checkout history
            'checked_out'     => 'sometimes|boolean',   // find only outstanding assets
            'type'            => 'sometimes|string',    // find for a type (aka description)
            'exclude'         => 'sometimes|string',    // exclude a type (aka description)
            'year'            => 'sometimes|integer',   // year to go searching in
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

        $asset = new Asset;
        $this->fromRest($asset);

        if (!$asset->isBarcodeUnique()) {
            throw new \InvalidArgumentException("Barcode already exists for current year");
        }

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
        $this->authorize('show', $asset);
        return $this->success($asset);
    }

    /*
     * Update the specified resource in storage.
     */

    public function update(Request $request, Asset $asset)
    {
        $this->authorize('update', $asset);
        $this->fromRest($asset);

        if ($asset->isDirty('barcode') && !$asset->isBarcodeUnique()) {
            throw new \InvalidArgumentException('Barcode already exists for year');
        }

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
        AssetPerson::where('asset_id', $asset->id)->delete();

        return $this->restDeleteSuccess();
    }

    /*
     * Retrieve the asset history
     */

    public function history(Asset $asset)
    {
        $this->authorize('update', $asset);

        return response()->json([ 'asset_history' => AssetPerson::retrieveHistory($asset->id) ]);
    }

    /*
     * Checkout Asset
     */

     public function checkout()
     {
         $this->authorize('checkout', [ Asset::class ]);

         $params = request()->validate([
             'barcode'       => 'required|string',
             'person_id'     => 'required|integer',
             'year'          => 'sometimes|integer',
             'attachment_id' => 'sometimes|integer|nullable|exists:asset_attachment,id',
         ]);

         $year = $params['year'] ?? date('Y');

         $asset = Asset::findByBarcodeYear($params['barcode'], $year);

         if ($asset == null) {
             return response()->json([ 'status' => 'not-found' ]);
         }

         $asset_person = AssetPerson::findCheckedOutPerson($asset->id);
         if ($asset_person) {
             return response()->json([
                'status'    => 'checked-out',
                'asset_id'  => $asset->id,
                'person_id' => $asset_person->person_id,
                'callsign'  => $asset_person->person ? $asset_person->person->callsign : "Deleted #{$asset_person->person_id}",
                'checked_out' => (string) $asset_person->checked_out
            ]);
         }

         $row = new AssetPerson([
             'asset_id'     => $asset->id,
             'attachment_id' => $params['attachment_id'] ?? null,
             'checked_out'  => SqlHelper::now(),
             'person_id'    => $params['person_id'],
         ]);

         if (!$row->save()) {
             throw new \InvalidArgumentException("Unknown error trying to create checkout record. ".$row->getErrors());
         }

         return response()->json([ 'status' => 'success' ]);
     }

     /*
      * Check in an asset
      */

      public function checkin(Asset $asset) {
          $this->authorize('checkin', [ Asset::class ]);

          $asset_person = AssetPerson::findCheckedOutPerson($asset->id);

          if (!$asset_person) {
              throw new \InvalidArgumentException("Asset is not checked out");
          }

          $asset_person->checked_in = SqlHelper::now();

          if (!$asset_person->save()) {
              throw new \InvalidArgumentException("Cannot check asset in");
          }

          return response()->json([ 'status' => 'success', 'checked_in' => (string) $asset_person->checked_in ]);
      }
}

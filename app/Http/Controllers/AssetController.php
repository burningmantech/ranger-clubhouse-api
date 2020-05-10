<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Http\Controllers\ApiController;
use App\Models\AssetPerson;
use App\Helpers\SqlHelper;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AssetController extends ApiController
{
    /**
     * Display assets based on criteria
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function index()
    {
        $this->authorize('index', Asset::class);

        $query = request()->validate([
            'barcode' => 'sometimes|string',    // specific barcode to find
            'include_history' => 'sometimes|boolean',   // include checkout history
            'checked_out' => 'sometimes|boolean',   // find only outstanding assets
            'type' => 'sometimes|string',    // find for a type (aka description)
            'exclude' => 'sometimes|string',    // exclude a type (aka description)
            'year' => 'sometimes|integer',   // year to go searching in
        ]);

        $assets = Asset::findForQuery($query);
        return $this->success($assets, null, 'asset');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function store()
    {
        $this->authorize('store', Asset::class);

        $asset = new Asset;
        $this->fromRest($asset);

        if ($asset->save()) {
            return $this->success($asset);
        }

        return $this->restError($asset);
    }

    /**
     * Display the specified resource.
     * @param Asset $asset
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(Asset $asset)
    {
        $this->authorize('show', $asset);
        return $this->success($asset);
    }

    /**
     * Update the specified resource in storage.
     * @param Asset $asset
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function update(Asset $asset)
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
        AssetPerson::where('asset_id', $asset->id)->delete();

        return $this->restDeleteSuccess();
    }

    /**
     * Retrieve the asset history
     *
     * @param Asset $asset
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function history(Asset $asset)
    {
        $this->authorize('history', $asset);
        return response()->json(['asset_history' => AssetPerson::retrieveHistory($asset->id)]);
    }

    /**
     * Checkout Asset
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function checkout()
    {
        $this->authorize('checkout', [Asset::class]);

        $params = request()->validate([
            'barcode' => 'required|string',
            'person_id' => 'required|integer',
            'year' => 'sometimes|integer',
            'attachment_id' => 'sometimes|integer|nullable|exists:asset_attachment,id',
        ]);

        $year = $params['year'] ?? current_year();

        $asset = Asset::findByBarcodeYear($params['barcode'], $year);

        if ($asset == null) {
            return response()->json(['status' => 'not-found']);
        }

        $asset_person = AssetPerson::findCheckedOutPerson($asset->id);
        if ($asset_person) {
            return response()->json([
                'status' => 'checked-out',
                'asset_id' => $asset->id,
                'person_id' => $asset_person->person_id,
                'callsign' => $asset_person->person ? $asset_person->person->callsign : "Deleted #{$asset_person->person_id}",
                'checked_out' => (string)$asset_person->checked_out
            ]);
        }

        $row = new AssetPerson([
            'asset_id' => $asset->id,
            'attachment_id' => $params['attachment_id'] ?? null,
            'checked_out' => SqlHelper::now(),
            'person_id' => $params['person_id'],
        ]);

        if (!$row->save()) {
            return $this->restError($row);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Check in an asset
     * @param Asset $asset
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function checkin(Asset $asset)
    {
        $this->authorize('checkin', [Asset::class]);
        $asset_person = AssetPerson::findCheckedOutPerson($asset->id);
        if (!$asset_person) {
            throw new InvalidArgumentException("Asset is not checked out");
        }

        $asset_person->checked_in = now();

        if (!$asset_person->save()) {
            return $this->restError($asset);
        }

        return response()->json(['status' => 'success', 'checked_in' => (string)$asset_person->checked_in]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Exceptions\UnacceptableConditionException;
use App\Models\Asset;
use App\Models\AssetPerson;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AssetController extends ApiController
{
    /**
     * Find assets based on the given criteria
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $this->authorize('index', Asset::class);

        $query = request()->validate([
            'barcode' => 'sometimes|string',        // specific barcode to find
            'checked_out' => 'sometimes|boolean',   // find only outstanding assets
            'entity_assignment' => 'sometimes|string', // person or group
            'exclude' => 'sometimes|string',        // exclude a type (aka description)
            'group_name' => 'sometimes|string',
            'include_history' => 'sometimes|boolean',   // include checkout history
            'order_number' => 'sometimes|string', // the vendor order number
            'type' => 'sometimes|string',    // find for a type (aka description)
            'year' => 'sometimes|integer',   // year to go searching in
        ]);

        $assets = Asset::findForQuery($query);
        return $this->success($assets, null, 'asset');
    }

    /**
     * Create a new asset
     *
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function store(): JsonResponse
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
     * Show an asset
     *
     * @param Asset $asset
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(Asset $asset): JsonResponse
    {
        $this->authorize('show', $asset);
        return $this->success($asset);
    }

    /**
     * Update an asset
     *
     * @param Asset $asset
     * @return JsonResponse
     * @throws AuthorizationException|ValidationException
     */

    public function update(Asset $asset): JsonResponse
    {
        $this->authorize('update', $asset);
        $this->fromRest($asset);

        if ($asset->save()) {
            return $this->success($asset);
        }

        return $this->restError($asset);
    }

    /**
     * Delete an asset
     *
     * @param Asset $asset
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(Asset $asset): JsonResponse
    {
        $this->authorize('delete', $asset);
        $asset->delete();
        return $this->restDeleteSuccess();
    }

    /**
     * Retrieve the asset history
     *
     * @param Asset $asset
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function history(Asset $asset): JsonResponse
    {
        $this->authorize('history', $asset);
        return response()->json(['asset_history' => AssetPerson::retrieveHistory($asset->id)]);
    }

    /**
     * Checkout Asset
     *
     * @return JsonResponse
     * @throws AuthorizationException|UnacceptableConditionException
     */

    public function checkout(): JsonResponse
    {
        $this->authorize('checkout', [Asset::class]);

        $params = request()->validate([
            'barcode' => 'required|string',
            'person_id' => 'required|integer',
            'year' => 'sometimes|integer',
            'attachment_id' => 'sometimes|integer|nullable|exists:asset_attachment,id',
            'force' => 'sometimes|boolean',
        ]);

        $force = $params['force'] ?? false;

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
                'checked_out' => (string)$asset_person->checked_out,
                'barcode' => $asset->barcode,
            ]);
        }

        if (!empty($asset->entity_assignment)) {
            return response()->json([
                'status' => 'entity-assigned',
                'asset_id' => $asset->id,
                'entity' => $asset->entity_assignment,
                'barcode' => $asset->barcode,
            ]);
        }

        $wasForced = false;
        if ($asset->has_expired) {
            if (!$force) {
                return response()->json([
                    'status' => 'expired',
                    'asset_id' => $asset->id,
                    'expires_on' => (string)$asset->expires_on,
                    'barcode' => $asset->barcode,
                ]);
            } else {
                $wasForced = true;
            }
        }

        $row = new AssetPerson([
            'asset_id' => $asset->id,
            'attachment_id' => $params['attachment_id'] ?? null,
            'checked_out' => now(),
            'person_id' => $params['person_id'],
            'check_out_person_id' => $this->user->id,
            'check_out_forced' => $wasForced,
        ]);

        if (!$row->save()) {
            return $this->restError($row);
        }

        return response()->json(['status' => 'success', 'asset' => $asset]);
    }

    /**
     * Check in an asset
     *
     * @param Asset $asset
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function checkin(Asset $asset): JsonResponse
    {
        $this->authorize('checkin', [Asset::class]);
        $asset_person = AssetPerson::findCheckedOutPerson($asset->id);
        if (!$asset_person) {
            throw new UnacceptableConditionException("Asset is not checked out");
        }

        $asset_person->checked_in = now();
        $asset_person->check_in_person_id = $this->user->id;

        if (!$asset_person->save()) {
            return $this->restError($asset);
        }

        return response()->json(['status' => 'success', 'checked_in' => (string)$asset_person->checked_in]);
    }
}

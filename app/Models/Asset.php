<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class Asset extends ApiModel
{
    protected $table = 'asset';

    protected $auditModel = true;

    protected $fillable = [
        'description',
        'barcode',
        'temp_id',
        'perm_assign',

        // Vehicle parameters Not used since the 2015 event.
        'subtype',
        'model',
        'color',
        'style',
        'category',
        'notes',
    ];

    protected $casts = [
        'perm_assign' => 'boolean',
        'new_user_eligible' => 'boolean',
        'on_sl_report' => 'boolean',
    ];

    protected $dates = [
        'create_date'
    ];

    protected $rules = [
        'barcode' => 'required|string|max:25',
        'temp_id' => 'sometimes|string|max:25',
        'subtype' => 'sometimes|string|max:25',
        'model' => 'sometimes|string|max:25',
        'color' => 'sometimes|string|max:25',
        'style' => 'sometimes|string|max:25',
        'category' => 'sometimes|string|max:25',
    ];


    public function asset_person(): BelongsTo
    {
        return $this->belongsTo(AssetPerson::class);
    }

    public function asset_history(): HasMany
    {
        return $this->hasMany(AssetPerson::class, 'asset_id')->orderBy('checked_out');
    }

    public function checked_out(): HasOne
    {
        return $this->hasOne(AssetPerson::class, 'asset_id')->whereNull('checked_in');
    }

    /**
     * Find assets based on the given criteria
     *
     * @param $query
     * @return Collection
     */

    public static function findForQuery($query): Collection
    {
        $year = $query['year'] ?? current_year();
        $sql = self::whereYear('create_date', $year);

        if (isset($query['barcode'])) {
            $sql = $sql->where('barcode', $query['barcode']);
        }

        if (isset($query['exclude'])) {
            $sql = $sql->where('description', '!=', $query['exclude']);
        }

        if (isset($query['type'])) {
            $sql = $sql->where('description', $query['type']);
        }

        if (isset($query['checked_out'])) {
            $sql = $sql->whereRaw('EXISTS (SELECT 1 FROM asset_person WHERE asset_person.asset_id=asset.id AND asset_person.checked_in IS NULL LIMIT 1)');
            $sql = $sql->with(['checked_out', 'checked_out.person:id,callsign', 'checked_out.attachment']);
        } else if (isset($query['include_history'])) {
            $sql = $sql->with([
                'asset_history',
                'asset_history.person:id,callsign',
                'asset_history.attachment'
            ]);
        }

        return $sql->orderBy('barcode')->get();
    }

    /**
     * Find an asset based on a barcode and year
     *
     * @param string $barcode
     * @param int $year
     * @return ?Asset
     */

    public static function findByBarcodeYear(string $barcode, int $year): ?Asset
    {
        return self::where('barcode', $barcode)
            ->whereYear('create_date', $year)
            ->first();
    }

    /**
     * Save / Update an asset record. Check to ensure no duplicate barcodes will be created.
     *
     * @param $options
     * @return bool
     */

    public function save($options = []): bool
    {
        // Ensure the barcode is unique for the year
        if (!$this->exists || $this->isDirty('barcode') || $this->isDirty('create_date')) {
            $model = $this; // can't "function A use ($this) { }", grr.
            $this->rules['barcode'] = [
                'required',
                'string',
                Rule::unique('asset')->where(function ($q) use ($model) {
                    $q->where('barcode', $model->barcode);
                    $q->whereYear('create_date', ($model->create_date ? $model->create_date->year : current_year()));
                    if ($model->exists) {
                        $q->where('id', '!=', $model->id);
                    }
                })
            ];
        }

        return parent::save($options);
    }
}

<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Models\AssetPerson;

class Asset extends ApiModel
{
    protected $table = 'asset';

    protected $fillable = [
        'description',
        'barcode',
        'temp_id',
        'perm_assign',
        'create_date',
        'subtype',
        'model',
        'color',
        'style',
        'category',
        'notes',
    ];

    protected $casts = [
        'perm_assign'       => 'boolean',
        'new_user_eligible' => 'boolean',
        'on_sl_report'      => 'boolean',
        'create_date'       => 'datetime'
    ];

    protected $rules = [
        'barcode' => 'required',
    ];

    public function asset_person() {
        return $this->belongsTo(AssetPerson::class);
    }

    public function asset_history() {
        return $this->hasMany(AssetPerson::class, 'asset_id')->orderBy('checked_out');
    }

    public function checked_out() {
        return $this->hasOne(AssetPerson::class, 'asset_id')->whereNull('checked_in');
    }

    public static function findForQuery($query) {
        $year = $query['year'] ?? date('Y');
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
            $sql = $sql->with([ 'checked_out', 'checked_out.person:id,callsign', 'checked_out.attachment' ]);
        } else if (isset($query['include_history'])) {
            $sql = $sql->with([
                'asset_history',
                'asset_history.person:id,callsign',
                'asset_history.attachment'
            ]);
        }

        return $sql->orderBy('barcode')->get();
    }

    public static function findByBarcodeYear($barcode, $year) {
        return self::where('barcode', $barcode)
                ->whereYear('create_date', $year)
                ->first();
    }

}

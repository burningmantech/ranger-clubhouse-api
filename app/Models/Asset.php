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
        'perm_assign'       => 'bool',
        'new_user_eligible' => 'bool',
        'on_sl_report'      => 'bool',
        'create_date'       => 'timestamp'
    ];

    protected $rules = [
        'barcode' => 'required',
    ];

    public function asset_person() {
        return $this->hasMany('App\Models\AssetPerson');
    }

    public static function findForQuery($query)
    {
        if (isset($query['barcode'])) {
            $sql = self::where('barcode', $query['barcode']);
        } else {
            $sql = DB::table('asset');
        }

        $year = isset($query['year']) ? $query['year'] : date('Y');
        $sql = $sql->whereYear('create_date', $year);

        if (isset($query['include_history'])) {
            $sql = $sql->with([
                'asset_person',
                'asset_person.person:id,callsign',
                'asset_person.attachment'
            ]);
        }

        return $sql->get();
    }
}

<?php

namespace App\Models;

use App\Models\ApiModel;

class AssetAttachment extends ApiModel
{
    protected $table = 'asset_attachment';
    protected $auditModel = true;

    protected $fillable = [
        'parent_type',
        'description',
    ];

    protected $rules = [
        'parent_type' => 'required|string',
        'description'  => 'required|string',
    ];


    public static function findAll()
    {
        return self::orderBy('description')->get();
    }
}

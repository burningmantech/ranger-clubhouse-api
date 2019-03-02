<?php

namespace App\Models;

use App\Models\ApiModel;

class Setting extends ApiModel
{
    protected $table = 'setting';

    // Allow all fields to be filled.
    protected $guarded = [];

    protected $rules = [
        'name' => 'required|string',
        'type' => 'required|string'
    ];

    public static function findAll()
    {
        return self::orderBy('name')->get();
    }

    public static function get($name)
    {
        $row = self::select('value', 'type')
                ->where('name', $name)
                ->first();

        if (!$row || $row->environment_only) {
            // No period means look it up in the clubhouse config tree.
            return config(self::fullName($name));
        }

        // Convert the values
        switch ($row->type) {
            case 'bool':
                return filter_var($row->value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int)$row->value;
            default:
                return $row->value;
        }
    }

    public static function fullName($name) {
        return strpos($name, '.') === false ? ('clubhouse.'.$name)  : $name;
    }
}

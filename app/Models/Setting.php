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
        if (is_array($name)) {
            $rows = self::select('name', 'value', 'type')->whereIn('name', $name)->get()->keyBy('name');
            $settings = [];
            foreach ($name as $setting) {
                $row = $rows[$setting] ?? null;
                if (!$row || $row->environment_only) {
                    // No period means look it up in the clubhouse config tree.
                    $value = config(self::fullName($setting));
                } else {
                    $value = $row->castedValue();
                }

                $settings[$setting] = $value;
            }

            return $settings;
        } else {
            $row = self::select('value', 'type')->where('name', $name)->first();

            if (!$row || $row->environment_only) {
                // No period means look it up in the clubhouse config tree.
                return config(self::fullName($name));
            }

            return $row->castedValue();
        }
    }

    public function castedValue() {
        // Convert the values
        switch ($this->type) {
            case 'bool':
                return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int)$this->value;
            default:
                return $this->value;
        }
    }

    public static function fullName($name) {
        return strpos($name, '.') === false ? ('clubhouse.'.$name)  : $name;
    }
}

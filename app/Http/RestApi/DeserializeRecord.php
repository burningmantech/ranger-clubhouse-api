<?php

namespace App\Http\RestApi;

use Illuminate\Database\Eloquent\Model;

class DeserializeRecord
{
    protected $attributes;
    protected $record;

    public function __construct($request, Model $record)
    {
        $this->record = $record;
        $table = $record->getResourceSingle();
        $this->attributes = $request->input($table);

        if (empty($this->attributes)) {
            if (is_null($this->attributes)) {
                throw new \InvalidArgumentException("Missing resource identifier '$table' field in request");
            } else {
                $this->attributes = [];
            }
        }
    }

    public static function fromRest($request, $record, $authorizedUser = null)
    {
        (new DeserializeRecord($request, $record))->fillRecord($authorizedUser);
        return $record;
    }

    public function fillRecord($authorizedUser = null): void
    {
        $modelName = class_basename($this->record);

        $filterName = "\\App\\Http\\Filters\\$modelName"."Filter";
        $modelColumns = (new $filterName($this->record))->deserialize($authorizedUser);

        $filtered = [ ];

        foreach ($this->attributes as $column => $value) {
            if (in_array($column, $modelColumns)) {
                $filtered[$column] = $value;
            }
        }

        $this->record->fill($filtered);
    }
}

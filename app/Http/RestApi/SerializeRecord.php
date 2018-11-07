<?php

namespace App\Http\RestApi;

use Illuminate\Database\Eloquent\Model;

use Carbon\Carbon;

class SerializeRecord
{

    /*
     * The record to be serialized
     * @var Model
     */

     const DATETIME_FORMAT = 'Y-m-d H:i:s';
     const DATE_FORMAT = 'Y-m-d';

    protected $record;

    public function __construct(Model $record)
    {
        $this->record = $record;
    }

    /*
     * Construct a REST API response representing the given record.
     * if $authorizedUser argument is present this means a column filter
     * is to be consulted on which columns are to be allowed based
     * on the $authorizedUser's roles. Otherwise, all columns are returned.
     *
     * The filter to be used is from \App\Http\Filters\<ModelName>Filter
     *
     * @var Model (optional) $authorizedUser the person to filter against
     * @return array a REST API record
     */

    public function toRest(Model $authorizedUser = null): array
    {
        $modelName = class_basename($this->record);

        if ($authorizedUser) {
            $modelFilter = "\\App\\Http\\Filters\\".$modelName."Filter";
            $columns = (new $modelFilter($this->record))->serialize($authorizedUser);
        } else {
            $appends = $this->record->getAppends();
            $fillable = $this->record->getFillable();

            if (empty($appends) && empty($fillable)) {
                // Use the actual set attributes if appends & fillables are empty
                $columns = array_keys($this->record->getAttributes());
            } else {
                if ($fillable) {
                    $columns = $fillable;
                } else {
                    $columns = [];
                }

                if ($appends) {
                    $columns = array_merge($columns, $appends);
                }
            }
        }

        $attributes = [ 'id' => $this->record->getKey() ];

        $casts = $this->record->getCasts();
        foreach ($columns as $column) {
            $value = $this->record->$column;

            if ($value !== null) {
                if ($casts && isset($casts[$column])) {
                    switch ($casts[$column]) {
                    case 'date':
                        $value = $value->format(self::DATE_FORMAT);
                        break;
                    case 'datetime':
                        $value = $value->format(self::DATETIME_FORMAT);
                        break;

                    case 'timestamp':
                        $value = Carbon::createFromTimestamp($value)->format(self::DATETIME_FORMAT);
                        break;

                    default:
                        if ($value instanceof Carbon) {
                            $value = $value->format(self::DATETIME_FORMAT);
                        }
                        break;
                    }
                } else if ($value instanceof Carbon) {
                    $value = $value->format(self::DATETIME_FORMAT);
                }
            }

            $attributes[$column] = $value;
        }

        return $attributes;
    }

    public function toRestError()
    {
        $record = $this->record;
        $errors = $record->getErrors();
        $errorList = [];

        if ($errors) {
            foreach ($errors->keys() as $key) {
                foreach ($errors->get($key) as $message) {
                    $errorList[] = [
                        'message'   => $message,
                        'column'    => $key,
                    ];
                }
            }
        }

        return [ 'errors' => $errorList ];
    }
}

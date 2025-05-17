<?php

namespace App\Http\RestApi;

use Illuminate\Database\Eloquent\Model;

class SerializeRecord
{
    public function __construct(protected Model $record)
    {
    }

    /**
     * Construct a REST API response representing the given record.
     * if $authorizedUser argument is present this means a column filter
     * is to be consulted on which columns are to be allowed based
     * on the $authorizedUser's roles. Otherwise, all columns are returned.
     *
     * The filter to be used is from \App\Http\Filters\<ModelName>Filter
     *
     * @param Model|null $authorizedUser
     * @return array a REST API record
     */

    public function toRest(?Model $authorizedUser = null): array
    {
        $modelName = class_basename($this->record);
        $recordArray = $this->record->toArray();
        if ($authorizedUser) {
            $modelFilter = "\\App\\Http\\Filters\\" . $modelName . "Filter";
            $columns = (new $modelFilter($this->record))->serialize($authorizedUser);

            $key = $this->record->getKey();
            if (is_array($key)) {
                // Composite key.
                $result = $key;
            } else {
                $result = [$this->record->getKeyName() => $key];
            }
            foreach ($columns as $col) {
                $result[$col] = $recordArray[$col] ?? null;
            }
            return $result;
        } else {
            return $recordArray;
        }
    }

    public function toRestError(): array
    {
        $record = $this->record;
        $errors = $record->getErrors();
        $errorList = [];

        if ($errors) {
            foreach ($errors->keys() as $key) {
                foreach ($errors->get($key) as $message) {
                    $errorList[] = [
                        'message' => $message,
                        'column' => $key,
                    ];
                }
            }
        }

        return ['errors' => $errorList];
    }
}

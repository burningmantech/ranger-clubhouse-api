<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Factory as Validator;

use App\Http\RestApi\SerializeRecord;
use App\Http\RestApi\DeserializeRecord;

abstract class ApiModel extends Model //implements AuditableContract
{
    //use Auditable;

    /**
     * Don't use created_at/updated_at.
     *
     * @var bool
     */
    public $timestamps = false;

    protected $errors;

    protected $rules;
    protected $updateRules;
    protected $createRules;

    // Resource name for REST requests
    protected $resourceSingle;
    protected $resourceCollection;

    public static function recordExists($id) : bool
    {
        return get_called_class()::where('id', $id)->exists();
    }

    public function validate($rules = null)
    {
        if ($rules === null) {
            if ($this->exists) {
                $rules = $this->updateRules;
            } else {
                $rules = $this->createRules;
            }

            if ($rules === null) {
                $rules = $this->rules;
            }
        }

        if (empty($rules)) {
            return true;
        }

        if ($this->fillable) {
            $attributes = [];
            foreach ($this->fillable as $column) {
                $attributes[$column] = $this->$column;
            }
        } else {
            $attributes = $this->getAttributes();
        }

        $validator = \Validator::make($attributes, $rules);

        if ($validator->fails()) {
            $this->errors = $validator->errors();
            return false;
        }

        return true;
    }

    public function save($options = [])
    {
        if (!$this->validate()) {
            return false;
        }

        return parent::save($options);
    }

    public function saveWithoutValidation($options = [])
    {
        return parent::save($options);
    }

    public function getResults()
    {
        return $this->results;
    }

    public function getErrors()
    {
        return $this->errors ? $this->errors->getMessages() : null;
    }

    public function addError(string $column, string $message):void
    {
        if (!$this->errors) {
            $this->errors = new MessageBag();
        }

        $this->errors->add($column, $message);
    }

    public function getRules()
    {
        return $this->rules;
    }

    // Laravel does not have this!?
    public function getAppends()
    {
        return $this->appends;
    }

    // Get the changed columns

    public function getChangedValues()
    {
        $changes = [];
        foreach ($this->getDirty() as $field => $newData) {
            $changes[$field] = [ $this->getOriginal($field), $newData ];
        }

        return $changes;
    }

    /*
     * Return the name of the root resource for a collection when building a REST response or
     * filling a record from a REST request.
     */

    public function getResourceCollection() {
        return (empty($this->resourceCollection) ?  $this->getTable() : $this->resourceCollection);
    }

    /*
     * Return the name of the root resource for a single record when building a REST response or
     * filling a record from a REST request.
     */

    public function getResourceSingle() {
        return (empty($this->resourceSingle) ? $this->getTable() : $this->resourceSingle);
    }
}

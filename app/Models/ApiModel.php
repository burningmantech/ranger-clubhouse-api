<?php

namespace App\Models;

use App\Models\ActionLog;
use App\Models\Person;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Auth;

use Illuminate\Validation\Factory as Validator;

use App\Http\RestApi\SerializeRecord;
use App\Http\RestApi\DeserializeRecord;

use DateTimeInterface;


abstract class ApiModel extends Model
{

    /**
     * Don't use created_at/updated_at.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Audit the changes to the record?
     * @var bool
     */

    protected $auditModel = false;

    /**
     * The reason the record is being created or updated. Works with $auditModel
     * @var string
     */
    public $auditReason;

    protected $errors;

    protected $rules;
    protected $updateRules;
    protected $createRules;

    // Resource name for REST requests
    protected $resourceSingle;
    protected $resourceCollection;

    public static function boot() {
        parent::boot();
        self::saving(function ($model) {
            $model->_prepAudit();
        });

        self::saved(function ($model) {
            $model->_recordAudit();
         });
    }

    /**
     * Prepare to audit a record. If the record is existing, grab the changes
     * before the save happens. Otherwise, the entire record will be logged.
     */

    public function _prepAudit() {
        if (!$this->auditModel) {
            return;
        }

        if ($this->exists) {
            // Existing record -- grab the changes
            $this->auditChanges = $this->getChangedValues();
            $this->auditIsNew = false;
        } else {
            $this->auditIsNew = true;
        }
    }

    /**
     * Record changes to the record.
     *
     * The changes are logged as the event 'table-name-{create,update}'.
     */

    public function _recordAudit() {
        if (!$this->auditModel) {
            return;
        }

        $table = str_replace('_', '-', $this->getTable());
        if ($model->auditIsNew) {
            $data = $this->attributes;
            $event = $table .'-create';
        } else {
            $values = $this->auditChanges;
            if (empty($values)) {
                return; // Nothing to record
            }
            $data['id'] = $this->id;
            $event = $table .'-update';
        }

        if ($this instanceof Person) {
            $personId = $this->id;
        } else {
            $personId = ($this->attributes['person_id'] ?? null);
        }

        ActionLog::record(Auth::user(), $event, $this->auditReason, $data, $personId);
    }

    public function validate($rules = null, $throwOnFailure = false)
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
            if ($throwOnFailure) {
                throw new \Illuminate\Validation\ValidationException($validator);
            }
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

    public function saveOrThrow($options = [])
    {
        $this->validate(null, true); // throws if validation fails
        if (!parent::save($options)) {
            throw new \RuntimeException("Could not save $this");
        }
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

    public function getAppends()
    {
        return $this->appends;
    }

    /**
     * Grab the changed values. Used for auditing.
     *
     * @return array [ 'column-name' => [ 'oldValue', 'newValue' ]]
     */
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


    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}

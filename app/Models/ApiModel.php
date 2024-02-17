<?php

namespace App\Models;

use AllowDynamicProperties;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Class ApiModel
 * @package App\Models
 * @mixin \Illuminate\Database\Eloquent\Builder
 * @mixin Builder
 *
 * Declarations for phpstorm
 * @method static $this find(array|string|integer $id)
 * @method static $this findOrFail(array|string|integer $id)
 * @method static $this select(...$args)
 * @method static $this selectRaw(...$args)
 * @method static $this where(...$args)
 * @method static $this whereIn(...$args)
 * @method static $this whereIntegerInRaw(...$args)
 * @method static $this whereNotIn(...$args)
 * @method static $this whereYear(...$args)
 */

#[AllowDynamicProperties]
abstract class ApiModel extends Model
{
    use HasFactory;

    /**
     * Don't use created_at/updated_at.
     *
     * @var bool
     */
    public $timestamps = false;
    public bool $auditIsNew = false;
    public $auditChanges = null;
    public array $auditExclude = [];

    /**
     * The reason the record is being created or updated. Works with $auditModel
     * @var ?string
     */
    public ?string $auditReason = null;

    /**
     * Audit the changes to the record?
     * @var bool
     */

    protected bool $auditModel = false;
    protected $errors;

    protected $rules;
    protected $updateRules;
    protected $createRules;

    // Resource name for REST requests
    protected $resourceSingle;
    protected $resourceCollection;

    protected $virtualColumns;

    public static function boot()
    {
        parent::boot();
        self::saving(function ($model) {
            $model->_prepAudit();
        });

        self::saved(function ($model) {
            $model->_recordAudit(false);
        });

        self::deleted(function ($model) {
            $model->_recordAudit(true);
        });
    }

    /**
     * Prepare to audit a record. If the record is existing, grab the changes
     * before the save happens. Otherwise, the entire record will be logged.
     */

    public function _prepAudit()
    {
        if (!$this->auditModel) {
            return;
        }

        $this->auditIsNew = !$this->exists;
        if ($this->exists) {
            // Existing record -- grab the changes
            $this->auditChanges = $this->getChangedValues();
        }
    }

    /**
     * Grab the changed values. Used for auditing.
     *
     * @return array [ 'column-name' => [ 'oldValue', 'newValue' ]]
     */

    public function getChangedValues(): array
    {
        $changes = [];
        foreach ($this->getDirty() as $field => $newValue) {
            $oldValue = $this->getOriginal($field);
            if ($oldValue instanceof Carbon) {
                $oldValue = (string)$oldValue;
            }
            if ($newValue instanceof Carbon) {
                $newValue = (string)$newValue;
            }
            $changes[$field] = [$oldValue, $newValue];
        }

        return $changes;
    }

    public function getAuditedValues() : array {
        $data = $this->auditChanges;
        $this->_filterAuditExcluded($data);
        return $data;
    }

    /**
     * Record changes to the record.
     *
     * The changes are logged as the event 'table-name-{create,update}'.
     */

    public function _recordAudit($deleted = false)
    {
        if (!$this->auditModel) {
            return;
        }

        $isExisting = false;
        $table = str_replace('_', '-', $this->getTable());
        if ($deleted) {
            $data = $this->attributes;
            $event = 'delete';
        } else if ($this->auditIsNew) {
            $data = $this->attributes;
            $event = 'create';
        } else {
            $isExisting = true;
            $event = 'update';
            $data = $this->auditChanges;
        }

        $this->_filterAuditExcluded($data);
         if (empty($data)) {
            return; // nothing to record, punt.
        }

        if ($isExisting) {
            $keyName = $this->getKeyName();
            if (is_array($keyName)) {
                // composite key, combine into a single string key1:key2:key3
                $keyName = implode(':', $keyName);
            }
            $data[$keyName] = $this->getKey();
        }

        // Figure out the target.
        if ($this instanceof Person) {
            $personId = $this->id;
        } else {
            $personId = ($this->getAttribute('person_id') ?? null);
        }

        ActionLog::record(Auth::user(), $table . '-' . $event, $this->auditReason, $data, $personId);
    }

    public function _filterAuditExcluded(& $data): void
    {
        if (!empty($this->auditExclude)) {
            // exclude any columns
            foreach ($this->auditExclude as $column) {
                unset($data[$column]);
            }
        }
    }

    /**
     * Strip out any virtual / computed columns before attempting any save.
     *
     * @return void
     */

    private function beforeSaveSetup(): void
    {
        if (!empty($this->virtualColumns)) {
            $this->attributes = array_diff_key($this->attributes, array_flip($this->virtualColumns));
        }
    }

    /**
     * Attempt to save a record with validation.
     *
     * @param $options
     * @return bool
     * @throws ValidationException
     */

    public function save($options = []): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $this->beforeSaveSetup();
        return parent::save($options);
    }

    /**
     * Save a model. Throw an exception if the save failed.
     *
     * @param array $options
     * @return void
     * @throws ValidationException
     */

    public function saveOrThrow(array $options = []): void
    {
        $this->validate(null, true); // throws if validation fails
        if (!$this->save($options)) {
            throw new RuntimeException("Could not save $this");
        }
    }

    /**
     * Save a record without validation.
     *
     * @param array $options
     * @return bool
     */

    public function saveWithoutValidation(array $options = []): bool
    {
        $this->beforeSaveSetup();
        return parent::save($options);
    }

    /**
     * Validate the object, usually upon create or save.
     *
     * @param null $rules
     * @param bool $throwOnFailure
     * @return bool
     * @throws ValidationException
     */

    public function validate($rules = null, bool $throwOnFailure = false): bool
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

        $validator = Validator::make($this->getAttributes(), $rules);

        if ($validator->fails()) {
            $this->errors = $validator->errors();
            if ($throwOnFailure) {
                throw new ValidationException($validator);
            }
            return false;
        }

        return true;
    }


    public function getResults()
    {
        return $this->results;
    }

    public function getErrors()
    {
        return $this->errors ? $this->errors->getMessages() : null;
    }

    public function addError(string $column, string $message): void
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
     * Return the name of the root resource for a collection when building a REST response or
     * filling a record from a REST request.
     *
     * @return string
     */

    public function getResourceCollection(): string
    {
        return (empty($this->resourceCollection) ? $this->getTable() : $this->resourceCollection);
    }

    /**
     * Return the name of the root resource for a single record when building a REST response or
     * filling a record from a REST request.
     *
     * @return string
     */

    public function getResourceSingle(): string
    {
        return (empty($this->resourceSingle) ? $this->getTable() : $this->resourceSingle);
    }


    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param DateTimeInterface $date
     * @return string
     */

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function setAuditModel(bool $audit): void
    {
        $this->auditModel = $audit;
    }
}

<?php

namespace App\Models;

use App\Attributes\NullIfEmptyAttribute;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Asset extends ApiModel
{
    protected $table = 'asset';

    protected bool $auditModel = true;

    const string TYPE_GEAR = 'gear';
    const string TYPE_RADIO = 'radio';          // Hand held
    const string TYPE_TEMP_ID = 'temp-id';

    const string TYPE_DESKTOP_RADIO = 'desktop-radio';
    const string TYPE_MOBILE_RADIO = 'mobile-radio';
    const string TYPE_RADIO_CHARGER = 'radio-charger';

    // Deprecated types
    const string TYPE_AMBER = 'amber';     // Only used in 2013
    const string TYPE_KEY = 'key';         // Only used in 2013 & 2014
    const string TYPE_VEHICLE = 'vehicle'; // Only used from 2013 to 2015

    protected $fillable = [
        'barcode',
        'group_name',
        'description',
        'entity_assignment',
        'expires_on',
        'group_name',
        'notes',
        'order_number',
        'perm_assign',
        'type',
        'year',
    ];

    protected $appends = [
        'has_expired'
    ];


    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'expires_on' => 'date:Y-m-d',
            'perm_assign' => 'boolean',
        ];
    }

    protected $rules = [
        'barcode' => 'required|string|max:25',
        'group_name' => 'sometimes|nullable|string|max:25',
        'description' => 'sometimes|nullable|string|max:25',
        'entity_assignment' => 'sometimes|nullable|string',
        'expires_on' => 'sometimes|nullable|date',
        'order_number' => 'sometimes|nullable|string',
        'type' => 'required|string',
        'year' => 'required|integer',
    ];

    public function asset_person(): BelongsTo
    {
        return $this->belongsTo(AssetPerson::class);
    }

    public function asset_history(): HasMany
    {
        return $this->hasMany(AssetPerson::class, 'asset_id')->orderBy('checked_out');
    }

    public function checked_out(): HasOne
    {
        return $this->hasOne(AssetPerson::class, 'asset_id')->whereNull('checked_in');
    }

    public static function boot(): void
    {
        parent::boot();

        self::deleted(function ($model) {
            AssetPerson::where('asset_id', $model->id)->delete();
        });
    }

    /**
     * Find assets based on the given criteria
     *
     * @param $query
     * @return Collection
     */

    public static function findForQuery($query): Collection
    {
        $year = $query['year'] ?? current_year();
        $barcode = $query['barcode'] ?? null;
        $exclude = $query['exclude'] ?? null;
        $type = $query['type'] ?? null;
        $checkedOut = $query['checked_out'] ?? null;
        $includeHistory = $query['include_history'] ?? null;
        $orderNumber = $query['order_number'] ?? null;
        $entityAssignment = $query['entity_assignment'] ?? null;
        $groupName = $query['group_name'] ?? null;

        $sql = self::where('year', $year);

        if ($barcode) {
            $sql->where('barcode', $barcode);
        }

        if ($exclude) {
            $sql->where('type', '!=', $exclude);
        }

        if ($type) {
            $sql->where('type', $type);
        }

        if ($orderNumber) {
            $sql->where('order_number', $orderNumber);
        }

        if ($entityAssignment) {
            $sql->where('entity_assignment', $entityAssignment);
        }

        if ($groupName) {
            $sql->where('group_name', $groupName);
        }

        if ($checkedOut) {
            $sql->whereRaw('EXISTS (SELECT 1 FROM asset_person WHERE asset_person.asset_id=asset.id AND asset_person.checked_in IS NULL LIMIT 1)');
            $sql->with([
                'checked_out',
                'checked_out.person:id,callsign',
                'checked_out.check_out_person:id,callsign',
                'checked_out.attachment'
            ]);
        } else if ($includeHistory) {
            $sql->with([
                'asset_history',
                'asset_history.person:id,callsign',
                'asset_history.check_out_person:id,callsign',
                'asset_history.check_in_person:id,callsign',
                'asset_history.attachment'
            ]);
        }

        return $sql->orderBy('barcode')->get();
    }

    /**
     * Find an asset based on a barcode and year
     *
     * @param string $barcode
     * @param int $year
     * @return ?Asset
     */

    public static function findByBarcodeYear(string $barcode, int $year): ?Asset
    {
        return self::where('barcode', $barcode)
            ->where('year', $year)
            ->first();
    }

    /**
     * Save / Update an asset record. Check to ensure no duplicate barcodes will be created.
     *
     * @param $options
     * @return bool
     * @throws ValidationException
     */

    public function save($options = []): bool
    {
        // Ensure the barcode is unique for the year
        if (!$this->exists || $this->isDirty('barcode') || $this->isDirty('year')) {
            $this->rules['barcode'] = [
                'required',
                'string',
                Rule::unique('asset')->where(function ($q) {
                    $q->where('barcode', $this->barcode);
                    $q->where('year', $this->year);
                    if ($this->exists) {
                        $q->where('id', '!=', $this->id);
                    }
                })
            ];
            $this->validateMessages = [
                'barcode.unique' => 'The barcode ' . $this->barcode . ' already exists for year ' . $this->year,
            ];
        }

        return parent::save($options);
    }

    /**
     * Has the asset expired?
     *
     * @return Attribute
     */

    public function hasExpired(): Attribute
    {
        return Attribute::make(
            get: fn(mixed $value, array $attributes) => $attributes['expires_on'] && Carbon::parse($attributes['expires_on'])->lte(now()),
        );
    }

    public function orderNumber(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function groupName(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function expiresOn(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function description(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function entityAssignment(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }
}

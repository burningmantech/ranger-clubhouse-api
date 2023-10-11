<?php

namespace App\Models;

use App\Attributes\BlankIfEmptyAttribute;
use App\Attributes\NullIfEmptyAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class HandleReservation extends ApiModel
{
    use HasFactory;

    protected $table = 'handle_reservation';
    protected bool $auditModel = true;
    public $timestamps = true;

    // Handle reservation types
    const TYPE_BRC_TERM = 'brc_term';
    const TYPE_DECEASED_PERSON = 'deceased_person';
    const TYPE_DISMISSED_PERSON = 'dismissed_person';
    const TYPE_RADIO_JARGON = 'radio_jargon';
    const TYPE_RANGER_TERM = 'ranger_term';
    const TYPE_SLUR = 'slur';
    const TYPE_TWII_PERSON = 'twii_person';
    const TYPE_UNCATEGORIZED = 'uncategorized';

    // Not a record type, used for the handle checker
    const TYPE_PHONETIC_ALPHABET = 'phonetic-alphabet';

    protected $fillable = [
        'handle',
        'reservation_type',
        'expires_on',
        'reason',
        'twii_year',
    ];

    protected $casts = [
        'expires_on' => 'datetime:Y-m-d',
    ];

    protected $rules = [
        'handle' => [
            'required',
            'string', '
            max:100'
        ],
        'reservation_type' => 'required|string|max:100',
        'expires_on' => 'sometimes|date:Y-m-d|after:start_date|nullable',
        'reason' => 'sometimes|string|max:255',
        'twii_year' => 'sometimes|integer|nullable|required_if:reservation_type,' . self::TYPE_TWII_PERSON,
    ];

    protected $attributes = [
        'reason' => '',
    ];

    protected $appends = [
        'has_expired'
    ];

    protected function endDate(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    protected function twiiYear(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    protected function reason(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    protected function getHasExpiredAttribute(): bool
    {
        return ($this->expires_on && now()->gt($this->expires_on));
    }

    public static function boot()
    {
        parent::boot();

        self::saving(function ($model) {
            if ($model->reservation_type == self::TYPE_TWII_PERSON && empty($model->expires_on)) {
                $model->expires_on = (current_year() + 2)."-09-15";
            }
        });
    }

    public function save($options = []): bool
    {
        $unique = Rule::unique('handle_reservation')
            ->where(fn($q) => $q->where('handle', $this->handle)->where('reservation_type', $this->reservation_type));

        if ($this->exists) {
            $unique->ignore($this->id);
        }

        if ($this->reservation_type == self::TYPE_TWII_PERSON) {
            $unique->where('twii_year', $this->twii_year);
        }

        $this->rules['handle'][] = $unique;

        return parent::save($options);
    }

    public static function findForQuery($query): Collection
    {
        $active = $query['active'] ?? null;
        $type = $query['reservation_type'] ?? null;
        $year = $query['twii_year'] ?? null;
        $expired = $query['expired'] ?? null;

        $sql = self::query();
        if ($active) {
            $sql->where(function ($q) {
                $q->where('expires_on', '>=', now());
                $q->orWhereNull('expires_on');
            });
        }

        if ($expired) {
            $sql->where('expires_on', '<', now());
        }

        if ($type) {
            $sql->where('reservation_type', $type);
        }

        if ($year) {
            $sql->where('twii_year', $year);
        }

        return $sql->orderBy('handle')->get();
    }

    public static function handleTypeExists(string $handle, string $type, ?int $twiiYear): bool
    {
        $sql = self::where(['handle' => $handle, 'reservation_type' => $type]);
        if ($type == self::TYPE_TWII_PERSON) {
            $sql->where('twii_year', $twiiYear);
        }
        return $sql->exists();
    }
}

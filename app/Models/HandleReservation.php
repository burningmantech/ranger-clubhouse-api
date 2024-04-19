<?php

namespace App\Models;

use App\Attributes\BlankIfEmptyAttribute;
use App\Attributes\NullIfEmptyAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class HandleReservation extends ApiModel
{
    use HasFactory;

    protected $table = 'handle_reservation';
    protected bool $auditModel = true;
    public $timestamps = true;

    // Handle reservation types
    const string TYPE_BRC_TERM = 'brc_term';
    const string TYPE_DECEASED_PERSON = 'deceased_person';
    const string TYPE_DISMISSED_PERSON = 'dismissed_person';
    const string TYPE_OBSCENE = 'obscene';
    const string TYPE_PHONETIC_ALPHABET = 'phonetic-alphabet';
    const string TYPE_RADIO_JARGON = 'radio_jargon';
    const string TYPE_RANGER_TERM = 'ranger_term';
    const string TYPE_SLUR = 'slur';
    const string TYPE_TWII_PERSON = 'twii_person';
    const string TYPE_UNCATEGORIZED = 'uncategorized';

    const array TYPE_LABELS = [
        self::TYPE_BRC_TERM => 'BRC Term',
        self::TYPE_DECEASED_PERSON => 'Deceased person',
        self::TYPE_DISMISSED_PERSON => 'Dismissed person',
        self::TYPE_OBSCENE => 'Obscene word or phrase',
        self::TYPE_PHONETIC_ALPHABET => 'Phonetic alphabet word',
        self::TYPE_RADIO_JARGON => 'Radio jargon',
        self::TYPE_RANGER_TERM => 'Ranger term',
        self::TYPE_SLUR => 'Slur',
        self::TYPE_TWII_PERSON => 'TWII Person',
        self::TYPE_UNCATEGORIZED => 'Uncategorized',
    ];

    protected $fillable = [
        'handle',
        'reservation_type',
        'expires_on',
        'reason',
        'twii_year',
    ];

    protected function casts(): array
    {
        return [
            'expires_on' => 'datetime:Y-m-d'
        ];
    }

    protected $rules = [
        'handle' => [
            'required',
            'string',
            'max:100'
        ],
        'reservation_type' => 'required|string|max:100',
        'expires_on' => 'sometimes|date:Y-m-d|nullable',
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

    protected function setHandleAttribute(?string $value): void
    {
        $value = trim($value ?: '');
        $this->attributes['handle'] = $value;
        $this->attributes['normalized_handle'] = Person::normalizeCallsign($value);
    }

    protected function getHasExpiredAttribute(): bool
    {
        return ($this->expires_on && now()->gt($this->expires_on));
    }

    public static function boot(): void
    {
        parent::boot();

        self::saving(function ($model) {
            if ($model->reservation_type == self::TYPE_TWII_PERSON && empty($model->expires_on)) {
                $model->expires_on = (current_year() + 2) . "-09-15";
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

    /**
     * Retrieve all active entries grouped by the normalized handle.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */

    public static function retrieveActiveByHandle(): \Illuminate\Database\Eloquent\Collection
    {
        return self::where(function ($q) {
            $q->where('expires_on', '>=', now());
            $q->orWhereNull('expires_on');
        })->get()
            ->groupBy('normalized_handle');
    }

    /**
     * Retrieve all active entries for a normalized handle.
     *
     * @param string $normalized
     * @return \Illuminate\Database\Eloquent\Collection
     */

    public static function retrieveAllByNormalizedHandle(string $normalized): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('normalized_handle', $normalized)
            ->where(function ($q) {
                $q->where('expires_on', '>=', now());
                $q->orWhereNull('expires_on');
            })->get();
    }

    public static function handleTypeExists(string $handle, string $type, ?int $twiiYear): bool
    {
        $sql = self::where([
            'normalized_handle' => Person::normalizeCallsign($handle),
            'reservation_type' => $type
        ])->where(function ($q) {
            $q->where('expires_on', '>=', now());
            $q->orWhereNull('expires_on');
        });

        if ($type == self::TYPE_TWII_PERSON) {
            $sql->where('twii_year', $twiiYear);
        }
        return $sql->exists();
    }

    /**
     * Record a deceased callsign.
     *
     * @param string $callsign
     * @return void
     */

    public static function recordDeceased(string $callsign): void
    {
        $exists = self::where('handle', $callsign)
            ->where('reservation_type', self::TYPE_DECEASED_PERSON)
            ->exists();

        if ($exists) {
            return;
        }

        self::insert([
            'handle' => $callsign,
            'reservation_type' => self::TYPE_DECEASED_PERSON,
            'expires_on' => (string)(now()->addYears(Person::GRIEVING_PERIOD_YEARS)),
            'reason' => 'marked deceased by ' . (Auth::user()->callsign ?? 'unknown') . ' on ' . now()->format('Y-m-d'),
        ]);
    }

    /**
     * Expire handles.
     *
     * @return Collection
     */

    public static function expireHandles(): Collection
    {
        $rows = HandleReservation::findForQuery(['expired' => true]);
        foreach ($rows as $row) {
            $row->auditReason = 'expire handle';
            $row->delete();
        }

        return $rows;
    }

    public function getTypeLabel(): string
    {
        return self::TYPE_LABELS[$this->reservation_type] ?? $this->reservation_type;
    }
}

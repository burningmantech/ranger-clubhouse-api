<?php

namespace App\Models;

use App\Attributes\BlankIfEmptyAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class PersonBanner extends ApiModel
{
    protected $table = 'person_banner';
    protected bool $auditModel = true;

    protected $fillable = [
        'person_id',
        'is_permanent',
        'message',
    ];

    protected $rules = [
        'person_id' => 'required|integer|exists:person,id',
        'message' => 'required|string',
        'is_permanent' => 'sometimes|boolean',
    ];

    public function casts(): array
    {
        return [
            'is_permanent' => 'bool',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function boot(): void
    {
        parent::boot();

        self::creating(function ($model) {
            if (!$model->creator_person_id) {
                $model->creator_person_id = Auth::id();
            }

            if (!$model->created_at) {
                $model->created_at = now();
            }
        });

        self::updating(function ($model) {
            $model->updater_person_id = Auth::id();
            $model->updated_at = now();
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function creator_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function updater_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Find notes based on the given query
     *
     * @param array $query
     * @return Collection
     */

    public static function findForQuery(array $query): Collection
    {
        $personId = $query['person_id'] ?? null;
        $year = $query['year'] ?? null;
        $active = $query['active'] ?? false;
        $includePerson = $query['include_person'] ?? false;

        $sql = self::query();

        if ($personId) {
            $sql->where('person_id', $personId);
        }

        if ($year) {
            $sql->whereYear('created_at', $year);
        }

        if (isset($query['is_permanent'])) {
            $sql->where('is_permanent', $query['is_permanent']);
        }

        if ($active) {
            $sql->where(function ($w) {
                $w->where('is_permanent', true);
                $w->orWhereYear('created_at', current_year());
            });
        }

        if ($includePerson) {
            $sql->with([
                'person:id,callsign,status',
                'creator_person:id,callsign',
                'updater_person:id,callsign'
            ]);
        }

        $rows = $sql->orderBy('created_at')->get();
        return $rows->sort(function ($a, $b) {
            $cmp = strcasecmp($a->person->callsign, $b->person->callsign);
            return $cmp ?: ($a->created_at->year - $b->created_at->year);
        })->values();
    }

    public function message(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonFka extends ApiModel
{
    protected $table = 'person_fka';

    public bool $auditModel = true;
    public $timestamps = false;

    protected $fillable = [
        'person_id',
        'fka'
    ];

    // Don't search on past prospective or auditor callsign formats.
    const string IRRELEVANT_REGEXP = '/(\S\d{2,4}|\d{2,4}B|\(NR\))$/';

    public $rules = [
        'fka' => 'required|string|max:255',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'is_irrelevant' => 'bool',
    ];

    public static function boot(): void
    {
        parent::boot();

        self::creating(function ($model) {
            $model->created_at = now();
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Find FKA records based on the given criteria
     *
     * @param $query
     * @return Collection
     */

    public static function findForQuery($query): Collection
    {
        $personId = $query['person_id'] ?? null;

        $sql = self::query();

        if ($personId) {
            $sql->where('person_id', $personId);
        }

        return $sql->orderBy('person_id')->orderBy('fka_normalized')->get();
    }

    /**
     * Update the FKA callsign list.
     */

    public static function addFkaToPerson(int $personId, ?string $oldCallsign, ?bool $irrelevant): void
    {
        if (empty($oldCallsign)) {
            return;
        }

        $query = ['person_id' => $personId, 'fka' => $oldCallsign];
        if (self::where($query)->exists()) {
            return;
        }

        $row = new PersonFka;
        $row->fka = $oldCallsign;
        $row->person_id = $personId;
        if ($irrelevant !== null) {
            $row->is_irrelevant = $irrelevant;
        } else {
            $row->is_irrelevant = (bool)preg_match(self::IRRELEVANT_REGEXP, $oldCallsign);
        }
        $row->saveWithoutValidation();
    }

    /**
     * Set the fka, and update the normalized version.
     * @param string|null $value
     * @return void
     */
    public function setFkaAttribute(?string $value): void
    {
        if (empty($value)) {
            $value = '';
        } else {
            $value = trim($value);
        }

        $this->attributes['fka'] = $value;
        $this->fka_normalized = Person::normalizeCallsign($value);
        $this->attributes['fka_soundex'] = metaphone(Person::spellOutNumbers($this->fka_normalized));
    }

    /**
     * Filter out irrelevant callsigns from an array.
     * i.e., bonked callsigns - <name>B<YY>, past prospective callsign <name><YY>, auditor callsigns <name> (NR)
     *
     * @param $names
     * @return array
     */

    public static function filterOutIrrelevant($names): array
    {
        if (empty($names)) {
            return [];
        }

        return array_values(array_filter($names, fn($name) => !preg_match(self::IRRELEVANT_REGEXP, $name)));
    }
}

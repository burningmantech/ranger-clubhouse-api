<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonCertification extends ApiModel
{
    protected $table = 'person_certification';
    public $timestamps = true;
    protected $auditModel = true;

    protected $fillable = [
        'person_id',
        'certification_id',
        'notes',
        'card_number',
        'issued_on',
        'trained_on',
    ];

    protected $rules = [
        'certification_id' => 'required|integer|exists:certification,id',
        'person_id' => 'required|integer',
        'recorder_id' => 'sometimes|integer|nullable',
        'notes' => 'sometimes|string|nullable',
        'card_number' => 'sometimes|string|nullable',
        'issued_on' => 'sometimes|date:Y-m-d|nullable',
        'trained_on' => 'sometimes|date:Y-m-d|nullable',
    ];

    protected $casts = [
        'issued_on' => 'date:Y-m-d',
        'trained_on' => 'date:Y-m-d',
    ];

    const RELATIONSHIPS = [
        'certification:id,title',
        'person:id,callsign',
        'recorder:id,callsign'
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    /**
     * Find a certification for a given person
     *
     * @param int $certificationId
     * @param int $personId
     * @return PersonCertification|null
     */

    public static function findCertificationForPerson(int $certificationId, int $personId) : ?PersonCertification
    {
        return self::where('person_id', $personId)->where('certification_id', $certificationId)->first();
    }

    /**
     * Find certifications
     *
     * @param array $query
     * @return Collection
     */

    public static function findForQuery(array $query): Collection
    {
        $personId = $query['person_id'] ?? null;
        $certificationId = $query['certification_id'] ?? null;

        $sql = self::query();
        if ($personId) {
            $sql->where('person_id', $personId);
        }

        if ($certificationId) {
            $sql->where('certification_id', $certificationId);
        }

        return $sql->with(self::RELATIONSHIPS)->get();
    }

    public function loadRelationships()
    {
        $this->load(self::RELATIONSHIPS);
    }

    public function setIssuedOnAttribute($value)
    {
        $this->attributes['issued_on'] = empty($value) ? null : $value;
    }

    public function setTrainedOnAttribute($value)
    {
        $this->attributes['trained_on'] = empty($value) ? null : $value;
    }
}

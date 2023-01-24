<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailHistory extends ApiModel
{
    use HasFactory;

    protected $table = 'email_history';
    public $timestamps = true;

    // Allow any fillable -- model is not directly accessible.
    protected $guarded = [];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function source_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Retrieve email history records based on the given criteria.
     *
     * @param $query
     * @return Collection
     */

    public static function findForQuery($query): Collection
    {
        $personId = $query['person_id'] ?? null;
        $sourcePersonId = $query['source_person_id'] ?? null;
        $email = $query['email'] ?? null;
        $includeSource = $query['include_source_person'] ?? null;

        $sql = self::query();

        if ($personId) {
            $sql->where('person_id', $personId);
        }

        if ($sourcePersonId) {
            $sql->where('source_person_id', $personId);
        }

        if ($includeSource) {
            $sql->with('source_person:id,callsign,status');
        }

        if ($email) {
            $sql->where('email', $email);
        }

        return $sql->orderBy('created_at')->get();
    }


    public static function record(int $personId, string $email, ?int $sourcePersonId)
    {
        self::create([
            'person_id' => $personId,
            'email' => $email,
            'source_person_id' => $sourcePersonId,
        ]);
    }
}

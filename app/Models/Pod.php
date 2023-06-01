<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Validation\ValidationException;

class Pod extends ApiModel
{
    use HasFactory;

    public $table = 'pod';
    public $timestamps = true;

    protected $fillable = [
        'type',
        'slot_id',
        'mentor_pod_id',
        'sort_index'
    ];

    public $casts = [
        'formed_at' => 'datetime',
        'disbanded_at' => 'datetime',
    ];

    public $rules = [
        'slot_id' => 'sometimes|nullable|integer|exists:slot,id',
    ];

    const RELATIONSHIPS = [
        'people',
        'past_people'
    ];

    const TYPE_SHIFT = 'shift';     // Normal shift, dirt pair or group
    const TYPE_MENTOR = 'mentor';   // Alpha Mentor pod
    const TYPE_MITTEN = 'mitten';   // Alpha  Mentor-In-Training pod
    const TYPE_ALPHA = 'alpha';     // Alpha pod

    public function person_pod(): HasMany
    {
        return $this->hasMany(PersonPod::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function people(): HasManyThrough
    {
        return $this->hasManyThrough(Person::class, PersonPod::class, 'pod_id', 'id', 'id', 'person_id')
            ->whereNull('person_pod.removed_at')
            ->select('person.id', 'person.callsign', 'person.status', 'person_pod.is_lead', 'person_pod.sort_index')
            ->orderBy('person_pod.sort_index')
            ->orderBy('person.callsign');
    }

    public function past_people(): HasManyThrough
    {
        return $this->hasManyThrough(Person::class, PersonPod::class, 'pod_id', 'id', 'id', 'person_id')
            ->whereNotNull('person_pod.removed_at')
            ->select('person.id', 'person.callsign', 'person.status', 'person_pod.is_lead')
            ->orderBy('person_pod.joined_at');
    }

    public static function boot(): void
    {
        parent::boot();

        self::creating(function ($model) {
            $model->formed_at = now();
        });
    }

    /**
     * Find some pods based on the query
     *
     * @param array $query
     * @return Collection
     */

    public static function findForQuery(array $query): Collection
    {
        $slotId = $query['slot_id'] ?? null;
        $year = $query['year'] ?? null;
        $type = $query['type'] ?? null;
        $includePeople = $query['include_people'] ?? null;

        $sql = self::query();

        if ($slotId) {
            $sql->where('slot_id', $slotId);
        }

        if ($year) {
            $sql->whereYear('formed_at', $year);
        }

        if ($type) {
            $sql->where('type', $type);
        }

        if ($includePeople) {
            $sql->with(self::RELATIONSHIPS);
        }

        return $sql->orderBy('sort_index')->get();
    }

    /**
     * Handle the case where a person is in a pod, and has gone off duty. Indicate the
     * person left (but not removed from) the pod.
     *
     * @param int $personId
     * @param int $timesheetId
     * @return void
     * @throws ValidationException
     */

    public static function shiftEnded(int $personId, int $timesheetId): void
    {
        $podPerson = PersonPod::findByPersonTimesheet($personId, $timesheetId);
        if (!$podPerson) {
            // Nothing to do.
            return;
        }

        $podPerson->left_at = now();
        $podPerson->save();

        $pod = Pod::find($podPerson->pod_id);
        if ($pod) {
            $pod->disbandIfEmpty();
        }
    }

    /**
     * Disband the pod if the current member count drops to zero.
     *
     * @return void
     */

    public function disbandIfEmpty(): void
    {
        $this->person_count = PersonPod::currentMemberCount($this->id);
        if (!$this->person_count) {
            $this->disbanded_at = now();
        }

        // Last person in the pod
        $this->saveWithoutValidation();
    }
}

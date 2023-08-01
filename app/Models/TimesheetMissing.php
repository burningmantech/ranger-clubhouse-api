<?php

namespace App\Models;

use App\Attributes\BlankIfEmptyAttribute;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * @property string $additional_notes
 * @property string $additional_reviewer_notes
 * @property Carbon $off_duty
 * @property Carbon $on_duty
 * @property ?string $partner
 * @property int $person_id
 * @property int $position_id
 * @property string $review_status
 * @property ?int $create_person_id
 * @property ?string|Carbon $reviewed_at
 */
class TimesheetMissing extends ApiModel
{
    protected $table = "timesheet_missing";
    protected bool $auditModel = true;

    const APPROVED = 'approved';
    const REJECTED = 'rejected';
    const PENDING = 'pending';

    protected $fillable = [
        'additional_notes',
        'additional_reviewer_notes',
        'off_duty',
        'on_duty',
        'partner',
        'person_id',
        'position_id',
        'review_status',

        // Used for creating new entries when review_status == 'approved'
        'create_entry',
        'new_on_duty',
        'new_off_duty',
        'new_position_id'
    ];

    protected $casts = [
        'create_entry' => 'boolean',
        'created_at' => 'datetime',
        'new_off_duty' => 'datetime',
        'new_on_duty' => 'datetime',
        'off_duty' => 'datetime',
        'on_duty' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected $appends = [
        'duration',
        'credits',
        'partner_info',
    ];

    protected $rules = [
        'on_duty' => 'required|date',
        'off_duty' => 'required|date|after:on_duty',
        'person_id' => 'required|integer',

        'create_entry' => 'sometimes|boolean|nullable',

        'new_on_duty' => 'date|nullable|required_if:create_entry,1',
        'new_off_duty' => 'date|nullable|after:new_on_duty|required_if:create_entry,1',
        'new_position_id' => 'integer|nullable|required_if:create_entry,1'
    ];

    public ?string $new_off_duty = null;
    public ?string $new_on_duty = null;
    public ?int $new_position_id = null;
    public bool $create_entry = false;

    const PARTNER_SHIFT_STARTS_WITHIN = 30;

    const RELATIONSHIPS = ['position:id,title', 'reviewer_person:id,callsign'];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function reviewer_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function create_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function partner_person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'partner', 'callsign');
    }

    /**
     * Find timesheet missing requests based on the criteria given.
     *
     * @param array $query
     * @return Collection
     */

    public static function findForQuery(array $query): Collection
    {
        $personId = $query['person_id'] ?? null;
        $year = $query['year'] ?? null;

        $sql = self::with(self::RELATIONSHIPS);

        if ($personId) {
            $sql->where('person_id', $personId);
        }

        if ($year) {
            $sql->whereYear('on_duty', $year);
        }

        return $sql->orderBy('on_duty')->get();
    }


    public function loadRelationships(): TimesheetMissing
    {
        return $this->load(self::RELATIONSHIPS);
    }

    /**
     * Get duration in seconds.
     *
     * @return int
     */

    public function getDurationAttribute(): int
    {
        $on_duty = $this->getOriginal('on_duty');
        $off_duty = $this->getOriginal('off_duty');

        return Carbon::parse($off_duty)->diffInSeconds(Carbon::parse($on_duty));
    }

    /**
     * Calculate how many credits this entry might be worth.
     *
     * @return float
     */

    public function getCreditsAttribute(): float
    {
        return PositionCredit::computeCredits(
            $this->position_id,
            $this->on_duty->timestamp,
            $this->off_duty->timestamp,
            $this->on_duty->year);
    }

    /**
     * Set the partner column.
      */

    public function partner(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    /**
     * Figure out who the partners are and what their shifts were that started within
     * a certain period of on_duty.
     *
     * Null is returned if partner is empty or matches 'na', 'n/a', 'none' or 'no partner'.
     *
     * If partner name contains ampersands, commas, or the word 'and', the name will be split
     * up and multiple searches run.
     *
     * For each name found will contain the following:
     *
     *  callsign, person_id (if callsign found)
     *    ... and the shift was found..
     *  on_duty, off_duty, position_id, position_title
     *
     * @return array|null
     */

    public function getPartnerInfoAttribute(): ?array
    {
        $name = preg_quote($this->partner, '/');
        if (empty($this->partner) || preg_grep("/^\s*{$name}\s*$/i", ['na', 'n/a', 'no partner', 'none'])) {
            return null;
        }

        $people = preg_split("/(\band\b|\s*[&\+\|]\s*)/i", $this->partner);

        $partners = [];

        foreach ($people as $name) {
            $name = trim($name);
            $sql = Person::where('callsign', $name);
            if (str_contains($name, ' ')) {
                $sql = $sql->orWhere('callsign', str_replace(' ', '', $name));
            }

            $partner = $sql->get(['id', 'callsign'])->first();

            if (!$partner) {
                // Try metaphone lookup
                $metaphone = metaphone($name);
                $partner = Person::where('callsign_soundex', $metaphone)->get(['id', 'callsign'])->first();
                if (!$partner) {
                    $partners[] = ['callsign' => $name];
                    continue;
                }
            }

            $partnerShift = Timesheet::findShiftWithinMinutes($partner->id, $this->on_duty, self::PARTNER_SHIFT_STARTS_WITHIN);
            if ($partnerShift) {
                $info = [
                    'timesheet_id' => $partnerShift->id,
                    'position_title' => $partnerShift->position->title,
                    'position_id' => $partnerShift->position_id,
                    'on_duty' => (string)$partnerShift->on_duty,
                    'off_duty' => (string)$partnerShift->off_duty
                ];
            } else {
                $info = [];
            }

            $info['callsign'] = $partner->callsign;
            $info['person_id'] = $partner->id;
            $info['name'] = $name;

            $partners[] = $info;
        }
        return $partners;
    }

    /*
     * Find the missing timesheet requests for a person OR all outstanding requests for a given year.
     *
     * Credits are calculated and the partner shift searched for.
     *
     * @param int $personId if null, find all request, otherwise find for person
     * @param int $year year to search
     * @return array found missing requests
     */

    public static function retrieveForPersonOrAllForYear($personId, $year)
    {
        $sql = self::with([
            'position:id,title',
            'person:id,callsign',
            'create_person:id,callsign',
            'reviewer_person:id,callsign',
            'partner_person:id,callsign'
        ])->whereYear('on_duty', $year)
            ->orderBy('on_duty');

        // Find for a person
        if ($personId !== null) {
            $sql = $sql->where('person_id', $personId);
        } else {
            $sql = $sql->where('review_status', self::PENDING);
        }

        $rows = $sql->get();

        return $rows->sortBy(fn($p) => $p->person->callsign, SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    /**
     * Set the create entry pseudo-column
     *
     * @param $value
     * @return void
     */

    public function setCreateEntryAttribute($value): void
    {
        $this->create_entry = $value;
    }

    /**
     * Set the new on duty pseudo-column
     *
     * @param $value
     * @return void
     */

    public function setNewOnDutyAttribute($value): void
    {
        $this->new_on_duty = $value;
    }

    /**
     * Set the new off duty pseudo-column
     *
     * @param $value
     * @return void
     */

    public function setNewOffDutyAttribute($value): void
    {
        $this->new_off_duty = $value;
    }

    /**
     * Set the new off position pseudo-column
     *
     * @param $value
     * @return void
     */

    public function setNewPositionIdAttribute($value): void
    {
        $this->new_position_id = $value;
    }

    /**
     * Append reviewer notes
     *
     * @param string|null $note
     * @return void
     */

    public function setAdditionalReviewerNotesAttribute(?string $note): void
    {
        $note = empty($note) ? null : trim($note);
        if (empty($note)) {
            return;
        }

        $callsign = Auth::user()?->callsign ?? '(unknown)';
        $date = date('Y/m/d H:m:s');
        $this->reviewer_notes = $this->reviewer_notes . "From $callsign on $date:\n$note\n\n";
    }

    /**
     * Append to requester notes
     *
     * @param string|null $note
     * @return void
     */

    public function setAdditionalNotesAttribute(?string $note): void
    {
        $note = empty($note) ? null : trim($note);
        if (empty($note)) {
            return;
        }

        $date = date('Y/m/d H:m:s');
        $this->notes = $this->notes . "$date:\n$note\n\n";
    }
}

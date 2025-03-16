<?php

namespace App\Models;

use App\Attributes\BlankIfEmptyAttribute;
use App\Attributes\PhoneAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProspectiveApplication extends ApiModel
{
    protected $table = 'prospective_application';

    public $timestamps = true;
    protected bool $auditModel = true;
    public array $auditExclude = [
        'updated_at',
        'created_at',
        'updated_by_person_id',
        'updated_by_person_at',
    ];

    const string STATUS_PENDING = 'pending';
    const string STATUS_APPROVED = 'approved';

    const string STATUS_CREATED = 'created';

    const string STATUS_MORE_HANDLES = 'more-handles';

    const string STATUS_HANDLE_CHECK = 'handle-check';

    const string STATUS_HOLD_AGE_ISSUE = 'age-issue';
    const string STATUS_HOLD_PII_ISSUE = 'pii-issue';
    const string STATUS_HOLD_QUALIFICATION_ISSUE = 'qualification-issue';
    const string STATUS_HOLD_RRN_CHECK = 'rrn-check';
    const string STATUS_HOLD_WHY_RANGER_QUESTION = 'why-ranger-question';

    const string STATUS_REJECT_PRE_BONK = 'reject-pre-bonk';
    const string STATUS_REJECT_UBERBONKED = 'reject-uber-bonked';
    const string STATUS_REJECT_TOO_YOUNG = 'reject-too-young';
    const string STATUS_REJECT_UNQUALIFIED = 'reject-unqualified';
    const string STATUS_REJECT_REGIONAL = 'reject-regional';

    const string  STATUS_REJECT_RETURNING_RANGER = 'reject-returning-ranger';

    const string STATUS_DUPLICATE = 'duplicate';

    const string EXPERIENCE_BRC1 = 'brc1';
    const string EXPERIENCE_BRC1R1 = 'brc1r1';
    const string EXPERIENCE_BRC2 = 'brc2';
    const string EXPERIENCE_NONE = 'none';

    const string WHY_VOLUNTEER_REVIEW_OKAY = 'okay';
    const string WHY_VOLUNTEER_REVIEW_PROBLEM = 'problem';
    const string WHY_VOLUNTEER_REVIEW_UNREVIEWED = 'unreviewed';

    const string API_ERROR_NONE = 'none';
    // Can see the contact record, but all the fields are blank.
    const string API_ERROR_CONTACT_BLANK = 'contact-blank';
    // Cannot see the contact record at all.
    const string API_ERROR_CONTACT_INACCESSIBLE = 'contact-inaccessible';
    // BPGUID is blank.
    const string API_ERROR_MISSING_BPGUID = 'missing-bgpuid';
    // Failed to insert into the database
    const string API_ERROR_CREATE_FAILURE = 'create-failure';

    // Record failed to validate before creation.
    const string API_ERROR_INVALID = 'invalid';

    // used only for Salesforce querying and importing
    public string $api_error = self::API_ERROR_NONE;
    public ?string $api_error_message = null;
    public ?string $contact_id = null;

    public ?array $screened_handles = null;

    protected $guarded = [
        'created_at',
        'updated_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'street' => '',
        'city' => '',
        'state' => '',
        'country' => '',
        'postal_code' => '',
        'phone' => '',
        'sfuid' => '',
    ];

    const array RELATIONSHIPS = [
        'audit_logs',
        'audit_logs.person:id,callsign',
        'mail_logs',
        'mail_logs.sender:id,callsign',
        'notes',
        'notes.person:id,callsign',
        'person:id,callsign,status',
        'bpguid_person:bpguid,id,callsign,status',    // Note, lookup is by BPGUID not record id.
        'assigned_person:id,callsign',
        'updated_by_person:id,callsign',
        'review_person:id,callsign'
    ];

    protected $appends = [
        'api_error',
        'contact_id',
        'screened_handles',
    ];


    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'is_over_18' => 'boolean',
            'rejected_handles' => 'array',
            'reviewed_at' => 'datetime',
            'updated_at' => 'datetime',
            'updated_by_person_at' => 'datetime',
        ];
    }

    public static function boot(): void
    {
        parent::boot();

        self::updating(function ($model) {
            $personId = Auth::id();
            if (!$personId) {
                return;
            }

            if ($model->isDirty('why_volunteer_review')) {
                $model->reviewed_at = now();
                $model->review_person_id = $personId;
            }

            $model->updated_by_person_id = $personId;
            $model->updated_by_person_at = now();
        });

        self::updated(function (ProspectiveApplication $model) {
            $changes = $model->getAuditedValues();
            if (!empty($changes)) {
                ProspectiveApplicationLog::record($model->id, ProspectiveApplicationLog::ACTION_UPDATED, $changes);
            }
        });

        self::deleted(function (ProspectiveApplication $model) {
            DB::table('prospective_application_log')->where('prospective_application_id', $model->id)->delete();
            DB::table('prospective_application_note')->where('prospective_application_id', $model->id)->delete();
        });
    }

    /**
     * The newly approved & created applicant account
     *
     * @return BelongsTo
     */

    // Account created for this application
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    // Account potentially associated with the applicant. Used to detect returning Rangers
    // who submitted an application mistakenly, or returning past prospectives.
    public function bpguid_person(): HasOne
    {
        return $this->hasOne(Person::class, 'bpguid', 'bpguid');
    }

    public function review_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ProspectiveApplicationNote::class);
    }

    public function audit_logs(): HasMany
    {
        return $this->hasMany(ProspectiveApplicationLog::class);
    }

    public function assigned_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function updated_by_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function loadRelationships(): void
    {
        $this->load(self::RELATIONSHIPS);
    }

    public function mail_logs(): HasMany
    {
        return $this->hasMany(MailLog::class);
    }

    public static function findForQuery($query): Collection
    {
        $year = $query['year'] ?? null;
        $status = $query['status'] ?? null;
        $contact = $query['contact'] ?? null;
        $personId = $query['person_id'] ?? null;

        $sql = self::query()->with(self::RELATIONSHIPS);

        if ($year) {
            $sql->where('year', $year);
        }

        if ($status) {
            $sql->where('status', $status);
        }

        if ($personId) {
            $sql->where('person_id', $personId);
        }

        if ($contact) {
            if (str_contains($contact, '@')) {
                $sql->where('email', $contact);
            } else {
                $sql->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', '%' . $contact . '%');
            }
        }

        $sql->orderBy($contact ? 'last_name' : 'id');

        return $sql->get();
    }

    /**
     * Find an application by the given year and SF application id (e.g., R-NNNN)
     *
     * @param int $year
     * @param string $salesforceName
     * @return ProspectiveApplication|null
     */

    public static function findByYearSalesforceName(int $year, string $salesforceName): ?ProspectiveApplication
    {
        return self::where(['year' => $year, 'salesforce_name' => $salesforceName])->first();
    }

    /**
     * Retrieve all approved applications
     *
     * @return Collection
     */

    public static function retrieveApproved(): Collection
    {
        return self::where('status', self::STATUS_APPROVED)
            ->where('year', current_year())
            ->get();
    }

    /**
     * Search for applications by application id, salesforce application id, email address, or name.
     *
     * @param string $query
     * @return array
     */

    public static function searchForApplications(string $query): array
    {
        if (str_starts_with($query, 'A-')) {
            $id = preg_replace("/^A-/", '', $query);
            $record = self::where('id', $id)->first();
            if ($record) {
                return [$record->toArray()];
            } else {
                return [];
            }
        } else if (str_starts_with($query, 'R-')) {
            return self::where('salesforce_name', $query)->orderBy('year', 'desc')->get()->toArray();
        } else if (str_contains($query, '@')) {
            return self::where('email', $query)->orderBy('year', 'desc')->get()->toArray();
        } else if (preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i", $query)) {
            return self::where('bpguid', $query)->orderBy('year', 'desc')->get()->toArray();
        }

        return self::where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', '%' . $query . '%')
            ->orderBy('year', 'desc')
            ->orderBy('last_name')
            ->get()
            ->toArray();
    }

    /**
     * Look up an application by the application id (e.g., A-NNN)
     *
     * @param string $applicationId
     * @return ProspectiveApplication
     */

    public static function findByApplicationIdOrFail(string $applicationId): ProspectiveApplication
    {
        $applicationId = preg_replace("/^A-/", '', $applicationId);
        return self::where('id', $applicationId)->with(self::RELATIONSHIPS)->firstOrFail();
    }

    /**
     * Return an array of qualified events attended (pandemic years 2020 and 2021 filter out)
     * @return array
     */

    public function qualifiedEvents(): array
    {
        $events = $this->allEvents();
        $year = current_year() - 10;
        return array_filter($events, fn($e) => ($e != 2020 && $e != 2021 && $e >= $year));
    }

    public function oldEvents(): array
    {
        $events = $this->allEvents();
        $year = current_year() - 10;
        return array_filter($events, fn($e) => ($e < $year));
    }

    public function allEvents(): array
    {
        if (empty($this->events_attended) || $this->events_attended == 'None') {
            return [];
        }

        return explode(';', $this->events_attended);
    }


    public function havePandemicYears(): bool
    {
        $events = $this->allEvents();
        return in_array(2020, $events) || in_array(2021, $events);
    }

    public function recordRejections(?string $message): void
    {
        $handles = explode("\n", $this->handles);
        if (empty($handles)) {
            return;
        }

        $rejects = [
            'rejected_at' => (string)now(),
            'handles' => $handles,
            'message' => $message,
            'rejected_by_id' => Auth::id(),
        ];

        if (empty($this->rejected_handles)) {
            $this->rejected_handles = [$rejects];
        } else {
            $this->rejected_handles = [
                $rejects,
                ...$this->rejected_handles,
            ];
        }

        $this->handles = '';
    }

    public function knownRangers(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function knownApplicants(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function whyVolunteer(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function whyVolunteerNotes(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function emergencyContact(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function approvedHandle(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function eventsAttended(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                return $value == 'None' ? '' : $value;
            },
            set: function ($value) {
                if ($value == 'None') {
                    return '';
                }
                return empty($value) ? '' : $value;
            },
        );
    }

    public function regionalExperience(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function handles(): Attribute
    {
        return Attribute::make(set: function ($value) {
            if (empty($value)) {
                return '';
            } else {
                $value = str_replace("\r", "", str_replace("\r\n", "\n", $value));
                return implode("\n", array_filter(explode("\n", $value), fn($l) => !empty(trim($l))));
            }
        });
    }

    public function experience(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function phone(): Attribute
    {
        return PhoneAttribute::make();
    }

    public function regionalCallsign(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function whyVolunteerReview(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function street(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function city(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function state(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function country(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function postalCode(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function getScreenedHandlesAttribute(): ?array
    {
        return $this->screened_handles;
    }

    public function getApiErrorAttribute(): ?string
    {
        return $this->api_error;
    }

    public function getApiErrorMessageAttribute(): ?string
    {
        return $this->api_error_message;
    }

    public function getContactIdAttribute(): ?string
    {
        return $this->contact_id;
    }

    public function bulkScreenHandles($reservedHandles): void
    {
        $this->buildScreenedHandles(fn($normalized) => $reservedHandles[$normalized] ?? null);
    }

    public function screenHandles(): void
    {
        $this->buildScreenedHandles(fn($normalized) => HandleReservation::retrieveAllByNormalizedHandle($normalized) ?? null);
    }

    public function buildScreenedHandles(callable $findReserved): void
    {
        $handles = explode("\n", $this->handles);
        $results = [];
        foreach ($handles as $handle) {
            $normalized = Person::normalizeCallsign($handle);
            $reserved = $findReserved($normalized);
            $person = DB::table('person')
                ->select('id', 'callsign', 'status', 'bpguid')
                ->where('callsign_normalized', $normalized)
                ->where(function ($sql) {
                    $sql->whereIn('status', [Person::ACTIVE, Person::INACTIVE, Person::INACTIVE_EXTENSION, Person::PROSPECTIVE, Person::ALPHA]);
                    $sql->orWhere('vintage', true);
                })
                ->first();
            $results[] = [
                'handle' => $handle,
                'reserved_handles' => $reserved,
                'existing_person' => $person,
                'is_applicant' => $person?->bpguid == $this->bpguid,
            ];
        }

        $this->screened_handles = $results;
    }

    /**
     * Return which PII fields are blanked. Support function for email.
     *
     * @return array
     */

    public function blankPersonalInfo(): array
    {
        $fields = [];
        if (empty($this->street)) {
            $fields[] = 'Street Address';
        }

        if (empty($this->city)) {
            $fields[] = 'City';
        }

        if (empty($this->country)) {
            $fields[] = 'Country';
        } else if (in_array($this->country, ['US', 'CA', 'AU']) && empty($this->state)) {
            if ($this->country === 'US') {
                $fields[] = 'State';
            } else {
                $fields[] = 'Province';
            }
        }

        if (empty($this->postal_code)) {
            $fields[] = 'Zip / Postal Code';
        }

        if (empty($this->phone)) {
            $fields[] = 'Telephone number';
        }

        return $fields;
    }
}

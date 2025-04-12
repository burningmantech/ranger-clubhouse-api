<?php

namespace App\Models;

use App\Attributes\BlankIfEmptyAttribute;
use App\Attributes\NullIfEmptyAttribute;
use App\Lib\YearsManagement;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class PersonAward extends ApiModel
{
    protected $table = 'person_award';
    protected bool $auditModel = true;
    public $timestamps = true;

    // The earliest an award is allowed to be given.
    const int FIRST_YEAR_PERMITTED = 1996;

    protected $fillable = [
        'award_id',
        'awards_grants_service_year',
        'notes',
        'person_id',
        'position_id',
        'team_id',
        'year',
    ];

    protected $attributes = [
        'notes' => '',
    ];

    const string MISSING_TYPE_ERROR = 'either award_id, position_id, team_id must be specified';

    public bool $bulkUpdate = false;

    public $rules = [
        'award_id' => 'sometimes|integer|nullable|exists:award,id',
        'awards_grants_service_year' => 'sometimes|boolean',
        'notes' => 'sometimes|string|nullable',
        'person_id' => 'required|integer|exists:person,id',
        'position_id' => 'sometimes|integer|nullable|exists:position,id',
        'team_id' => 'sometimes|integer|nullable|exists:team,id',
        'year' => 'required|integer',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function creator_person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id', 'id');
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(Award::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function loadRelationships(): PersonAward
    {
        return $this->load([
            'award:id,title',
            'position:id,title,active,type',
            'team:id,title,active',
            'person:id,callsign'
        ]);
    }

    public function casts(): array
    {
        return [
            'awards_grants_service_year' => 'boolean',
        ];
    }

    public static function boot(): void
    {
        parent::boot();

        self::creating(function ($model) {
            $model->creator_person_id = Auth::id();
        });

        self::saved(function (PersonAward $model) {
            if (!$model->bulkUpdate) {
                YearsManagement::updateYearsOfAwards($model->person_id);
            }
        });

        self::deleted(function (PersonAward $model) {
            if (!$model->bulkUpdate) {
                YearsManagement::updateYearsOfAwards($model->person_id);
            }
        });
    }

    public function save($options = []): bool
    {
        if (!$this->award_id && !$this->position_id && !$this->team_id) {
            $this->addError('award_id', self::MISSING_TYPE_ERROR);
            $this->addError('position_id', self::MISSING_TYPE_ERROR);
            $this->addError('team_id', self::MISSING_TYPE_ERROR);
            return false;
        }

        if ($this->isDirty(['award_id', 'position_id', 'team_id', 'year'])) {
            $column = null;
            if ($this->award_id) {
                $column = 'award_id';
            } else if ($this->position_id) {
                $column = 'position_id';
            } else if ($this->team_id) {
                $column = 'team_id';
            }

            // if no associated entity has been set, the default validation rules will fail.
            if ($column && $this->person_id && $this->year) {
                $value = $this->{$column};
                if (!empty($value)) {
                    $sql = self::where($column, $value)
                        ->where('person_id', $this->person_id)
                        ->where('year', $this->year);

                    if ($this->exists) {
                        $sql->where('id', '!=', $this->id);
                    }

                    if ($sql->exists()) {
                        $this->addError($column, 'award already exists for the year');
                        error_log("COLUMN $column -> [$value]");
                        return false;
                    }
                }
            }
        }

        if ($this->team_id && !$this->team?->awards_eligible) {
            $this->addError('team_id', 'Team is not eligible for awards');
            return false;
        } else if ($this->position_id && !$this->position?->awards_eligible) {
            $this->addError('position_id', 'Position is not eligible for awards');
            return false;
        }

        return parent::save($options);
    }

    /**
     * Find all the awards given to a person
     *
     * @param array $query
     * @return mixed
     */

    public static function findForQuery(array $query): mixed
    {
        $personId = $query['person_id'] ?? null;
        $awardId = $query['award_id'] ?? null;
        $positionId = $query['position_id'] ?? null;
        $teamId = $query['team_id'] ?? null;

        $sql = self::with(['award', 'team', 'person:id,callsign']);
        if ($personId) {
            $sql->where('person_id', $personId);
        }

        if ($awardId) {
            $sql->where('award_id', $awardId);
        }

        if ($positionId) {
            $sql->where('position_id', $positionId);
        }

        if ($teamId) {
            $sql->where('team_id', $teamId);
        }

        return $sql->get()->sort(function ($a, $b) {
            $result = strcasecmp($a->title(), $b->title());
            return !$result ? ($a->year - $b->year) : $result;
        })->values();
    }

    /**
     * Retrieve all awards given to a person. Classify into team and special service awards.
     * @param int $personId
     * @return array
     */

    public static function retrieveForPerson(int $personId): array
    {
        $rows = self::findForQuery(['person_id' => $personId]);

        $teams = [];
        $special = [];
        $positions = [];

        foreach ($rows as $row) {
            // Separate out team awards from service awards
            if ($row->team_id) {
                self::buildAward($row->team_id, $teams, 'team', $row);
            } else if ($row->position_id) {
                self::buildAward($row->position_id, $positions, 'position', $row);
            } else {
                self::buildAward($row->award_id, $special, 'award', $row);
            }
        }

        self::sortAwardGroup($positions);
        self::sortAwardGroup($teams);
        self::sortAwardGroup($special);

        return [
            'teams' => $teams,
            'special' => $special,
            'positions' => $positions,
        ];
    }

    public static function buildAward(int $id, array &$awards, string $table, PersonAward $row): void
    {
        if (!isset($awards[$id])) {
            $awards[$id] = [
                'id' => $id,
                'title' => $row->{$table}?->title ?? "Unknown award [$id]",
                'years' => [],
                'service_years' => [],
            ];
        }

        $awards[$id]['years'][] = $row->year;
        if ($row->awards_grants_service_year) {
            $awards[$id]['service_years'][] = $row->year;
        }
    }

    public static function sortAwardGroup(&$group): void
    {
        $group = array_values($group);
        usort($group, fn($a, $b) => strcasecmp($a['title'], $b['title']));
        foreach ($group as &$award) {
            sort($award['years'], SORT_NUMERIC);
            sort($award['service_years'], SORT_NUMERIC);
        }
    }

    /**
     * Does the person have a service award?
     *
     * @param int $personId
     * @param int $awardId
     * @param int $year
     * @return bool
     */

    public static function haveServiceAward(int $personId, int $awardId, int $year): bool
    {
        return self::where(['award_id' => $awardId, 'person_id' => $personId, 'year' => $year])->exists();
    }

    /**
     * Does the person have a team award?
     *
     * @param int $personId
     * @param int $teamId
     * @param int $year
     * @return bool
     */

    public static function haveTeamAward(int $personId, int $teamId, int $year): bool
    {
        return self::where(['team_id' => $teamId, 'person_id' => $personId, 'year' => $year])->exists();
    }

    /**
     * Does the person have a position award?
     *
     * @param int $personId
     * @param int $positionId
     * @param int $year
     * @return bool
     */

    public static function havePositionAward(int $personId, int $positionId, int $year): bool
    {
        return self::where(['position_id' => $positionId, 'person_id' => $personId, 'year' => $year])->exists();
    }

    /**
     * Set the notes
     */

    public function awardId(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function notes(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }

    public function positionId(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function teamId(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function title(): string
    {
        if ($this->team_id) {
            return $this->team?->title;
        } else if ($this->award_id) {
            return $this->award?->title;
        } else {
            return $this->position?->title;
        }
    }
}

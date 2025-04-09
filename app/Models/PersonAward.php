<?php

namespace App\Models;

use App\Attributes\BlankIfEmptyAttribute;
use App\Attributes\NullIfEmptyAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PersonAward extends ApiModel
{
    protected $table = 'person_award';
    protected bool $auditModel = true;
    public $timestamps = true;

    // The earliest an award is allowed to be given.
    const int FIRST_YEAR_PERMITTED = 1996;

    protected $fillable = [
        'award_id',
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

    public static function boot(): void
    {
        parent::boot();

        self::creating(function ($model) {
            $model->creator_person_id = Auth::id();
        });

        self::saved(function (PersonAward $model) {
            if (!$model->bulkUpdate) {
                self::updateYearsOfService($model->person_id);
            }
        });

        self::deleted(function (PersonAward $model) {
            if (!$model->bulkUpdate) {
                self::updateYearsOfService($model->person_id);
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
                        return false;
                    }
                }
            }
        }

        if ($this->team_id && !$this->team?->isAwardsEligible()) {
            $this->addError('team_id', 'Team is not eligible for awards');
            return false;
        } else if ($this->position_id && !$this->position?->awards_eligible) {
            $this->addError('position_id', 'Position is not eligible for awards');
            return false;
        }

        return parent::save($options);
    }

    /**
     * Update the years of service for a person.
     *
     * @param int $personId
     * @return void
     */

    public static function updateYearsOfService(int $personId): void
    {
        $years = DB::table('person_award')
            ->select('year')
            ->distinct()
            ->where('person_id', $personId)
            ->orderBy('year')
            ->get()
            ->pluck('year')
            ->toArray();

        Person::where('id', $personId)->update(['years_of_service' => $years]);
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
                $teamId = $row->team_id;
                if (!isset($teams[$teamId])) {
                    $teams[$teamId] = [
                        'id' => $teamId,
                        'title' => $row->team?->title ?? "Unknown team [$teamId]",
                        'years' => [],
                    ];
                }
                $teams[$teamId]['years'][] = $row->year;
            } else if ($row->position_id) {
                $positionId = $row->position_id;
                if (!isset($positions[$positionId])) {
                    $positions[$positionId] = [
                        'id' => $positionId,
                        'title' => $row->position?->title ?? "Unknown position [$positionId]",
                        'years' => [],
                    ];
                }
                $positions[$positionId]['years'][] = $row->year;
            } else {
                $awardId = $row->award_id;
                if (!isset($special[$awardId])) {
                    $special[$awardId] = [
                        'id' => $awardId,
                        'title' => $row->award?->title ?? "Unknown award [$awardId]",
                        'years' => [],
                    ];
                }

                $special[$awardId]['years'][] = $row->year;
            }
        }

        $positions = array_values($positions);
        $teams = array_values($teams);
        $special = array_values($special);

        usort($positions, fn($a, $b) => strcasecmp($a['title'], $b['title']));
        usort($teams, fn($a, $b) => strcasecmp($a['title'], $b['title']));
        usort($special, fn($a, $b) => strcasecmp($a['title'], $b['title']));

        foreach ($positions as &$position) {
            sort($position['years']);
        }

        foreach ($teams as &$team) {
            sort($team['years']);
        }

        foreach ($special as &$s) {
            sort($s['years']);
        }

        return [
            'teams' => $teams,
            'special' => $special,
            'positions' => $positions,
        ];
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

<?php

namespace App\Lib;

use App\Helpers\SqlHelper;
use App\Models\Person;
use App\Models\Position;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PersonSearch
{
    const FIELD_CALLSIGN = 'callsign';
    const FIELD_EMAIL = 'email';
    const FIELD_OLD_EMAIL = 'old-email';
    const FIELD_FKA = 'fka';
    const FIELD_ID = 'id';
    const FIELD_NAME = 'name';
    const FIELD_LAST_NAME = 'last';

    /*
     * Pseudo status - find any one who is signed up for a Cheetah Cub shift. Useful for when a HQ Window Worker
     * is handling a retired Ranger returning back.
     */

    const CHEETAH_CUB_STATUS = 'cheetahcub';

    const BASE_COLUMNS = [
        'person.id',
        'person.callsign',
        'person.status',
        'person.first_name',
        'person.last_name',
        'person.preferred_name',
    ];

    const STATUS_GROUPS = [
        [
            'title' => 'Active/Current',
            'statuses' => [
                Person::ACTIVE,
                Person::ALPHA,
                Person::INACTIVE,
                Person::INACTIVE_EXTENSION,
                Person::ECHELON,
                Person::PROSPECTIVE,
                Person::SUSPENDED,
            ]
        ],
        [
            'title' => 'Auditor',
            'statuses' => [
                Person::AUDITOR,
            ]
        ],
        [
            'title' => 'Resigned/Retired',
            'statuses' => [
                Person::RESIGNED,
                Person::RETIRED
            ]
        ],
        [
            'title' => 'Dismissed/Bonked',
            'statuses' => [
                Person::BONKED,
                Person::DISMISSED,
                Person::UBERBONKED
            ]
        ],
        [
            'title' => 'Past Prospective',
            'statuses' => [
                Person::PAST_PROSPECTIVE
            ],
        ],
        [
            'title' => 'Deceased',
            'statuses' => [
                Person::DECEASED
            ]
        ],
    ];


    public array $results = [];
    public int $limit;
    public int $offset;
    public string $query;
    public ?string $statuses;
    public ?string $excludeStatuses;

    /**
     * Perform a fuzzy search for a person based on the given query.
     *
     * @param array $query
     * @param bool $canViewEmail
     * @return array
     */

    public static function execute(array $query, bool $canViewEmail = false): array
    {
        // remove duplicate spaces
        $q = trim(preg_replace('/\s+/', ' ', $query['query']));

        if (empty($q)) {
            return [];
        }

        if (str_starts_with($q, '+')) {
            // Search by number
            $person = DB::table('person')->select(self::BASE_COLUMNS)->where('id', ltrim($q, '+'))->first();
            return [
                [
                    'field' => self::FIELD_ID,
                    'total' => $person ? 1 : 0,
                    'people' => $person ? [self::buildPerson($person, null)] : []
                ]
            ];
        }

        if ($query['status_groups'] ?? false) {
            return self::executeSearchStatusGroups($q, $query, $canViewEmail);
        } else {
            return self::executeQuery($q, $query, $canViewEmail);
        }
    }

    public static function executeSearchStatusGroups(string $q, array $query, bool $canViewEmail): array
    {
        $resultGroups = [];

        foreach (self::STATUS_GROUPS as $group) {
            $groupQuery = [...$query, 'statuses' => join(',', $group['statuses'])];
            $results = self::executeQuery($q, $groupQuery, $canViewEmail);
            if (!empty($results)) {
                $resultGroups[] = [
                    'title' => $group['title'],
                    'results' => $results
                ];
            }
        }

        return $resultGroups;
    }

    public static function executeQuery(string $q, array $query, bool $canViewEmail): array
    {
        $search = new self();
        $search->query = $q;
        $search->statuses = $query['statuses'] ?? null;
        $search->excludeStatuses = $query['exclude_statuses'] ?? null;
        $search->offset = $query['offset'] ?? 0;
        $search->limit = $query['limit'] ?? 10;

        $searchFields = $query['search_fields'] ?? null;

        $emailOnly = (stripos($q, '@') !== false);

        if ($emailOnly && $canViewEmail) {
            $search->searchEmail();
            $search->searchOldEmail();
        } else {
            foreach (explode(',', $searchFields) as $field) {
                switch ($field) {
                    case self::FIELD_CALLSIGN:
                        $search->searchCallsign();
                        break;

                    case self::FIELD_FKA:
                        $search->searchFka();
                        break;

                    case self::FIELD_NAME:
                        $search->searchName();
                        break;

                    case self::FIELD_LAST_NAME:
                        $search->searchLastName();
                        break;
                }
            }
        }

        return $search->results;
    }

    /**
     * Search for Callsigns
     */

    public function searchCallsign(): void
    {
        $callsign = $this->query;
        $sql = $this->baseSql();
        $normalized = Person::normalizeCallsign($callsign);
        $metaphone = metaphone(Person::spellOutNumbers($callsign));

        $sql->where(function ($q) use ($normalized, $metaphone) {
            $q->orWhere('callsign_normalized', $normalized);
            $q->orWhere('callsign_normalized', 'like', '%' . $normalized . '%');
            $q->orWhere('callsign_soundex', $metaphone);
            $q->orWhere('callsign_soundex', 'like', $metaphone . '%');
        });

        /*
         * Callsign sort priority is
         * - Exact callsign match
         * - Beginning of callsign match
         * - Substring callsign match
         * - Exact phonetic callsign match
         * - Beginning of phonetic match
         * - substring phonetic match
         * - Everything else
         */

        $orderBy = "CASE";
        $orderBy .= " WHEN callsign_normalized=" . SqlHelper::quote($normalized) . " THEN CONCAT('01', callsign)";
        $orderBy .= " WHEN callsign_normalized like " . SqlHelper::quote($normalized . '%') . " THEN CONCAT('02', callsign)";
        $orderBy .= " WHEN callsign_normalized like " . SqlHelper::quote('%' . $normalized . '%') . " THEN CONCAT('03', callsign)";
        $orderBy .= " WHEN callsign_soundex=" . SqlHelper::quote($metaphone) . " THEN CONCAT('04', callsign)";
        $orderBy .= " WHEN callsign_soundex like " . SqlHelper::quote($metaphone . '%') . " THEN CONCAT('05', callsign)";
        $orderBy .= " WHEN callsign_soundex like " . SqlHelper::quote('%' . $metaphone . '%') . " THEN CONCAT('06', callsign)";
        $orderBy .= " ELSE CONCAT('99', callsign) END";
        $sql->orderBy(DB::raw($orderBy));

        $this->addResult($this->runSql($sql, self::FIELD_CALLSIGN), ['callsign_normalized' => $normalized]);
    }

    /**
     * Search for name. Search against "first last"* or "last"*.
     */

    public function searchName(): void
    {
        $name = $this->query;
        $sql = $this->baseSql();

        $likeName = SqlHelper::quote('%' . $name . '%');
        $sql->where(function ($q) use ($likeName, $name) {
            $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE " . $likeName);
            $q->orWhere(function ($w) use ($likeName) {
                $w->where('preferred_name', '!=', '');
                $w->whereRaw("CONCAT(preferred_name, ' ', last_name) LIKE " . $likeName);
            });
            $q->orWhere('last_name', 'like', $name . '%');
        });

        $likeLastName = SqlHelper::quote($name . '%');
        $orderBy = "CASE";
        $orderBy .= " WHEN CONCAT(first_name, ' ', last_name) LIKE " . $likeName . " THEN CONCAT('12', first_name, ' ', last_name)";
        $orderBy .= " WHEN CONCAT(preferred_name, ' ', last_name) LIKE " . $likeName . " THEN CONCAT('12', preferred_name, ' ', last_name)";
        $orderBy .= " WHEN CONCAT(last_name) LIKE " . $likeLastName . " THEN CONCAT('11', 'last_name')";
        $orderBy .= "ELSE CONCAT('99', callsign) END";
        $sql->orderBy(DB::raw($orderBy));

        $this->addResult($this->runSql($sql, self::FIELD_NAME));
    }

    /**
     * Search on just the last name.
     */

    public function searchLastName(): void
    {
        $last = $this->query;
        $sql = $this->baseSql();

        $sql->where('last_name', 'like', $last . '%');
        $sql->orderBy('last_name');

        $this->addResult($this->runSql($sql, self::FIELD_LAST_NAME));
    }


    /**
     * Search for Formerly Known As callsigns
     */

    public function searchFka(): void
    {
        $normalized = Person::normalizeCallsign($this->query);
        $metaphone = metaphone(Person::spellOutNumbers($this->query));
        $sql = $this->baseSql();

        $sql->addSelect('person_fka.fka', 'person_fka.fka_normalized', 'person_fka.fka_soundex', 'callsign_normalized');
        $sql->join('person_fka', 'person_fka.person_id', 'person.id');
        $sql->where('person_fka.is_irrelevant', false);
        $sql->orderBy('callsign');

        $sql->where(function ($q) use ($normalized, $metaphone) {
            $q->orWhere('person_fka.fka_normalized', $normalized);
            $q->orWhere('person_fka.fka_normalized', 'like', '%' . $normalized . '%');
            $q->orWhere('person_fka.fka_soundex', $metaphone);
            $q->orWhere('person_fka.fka_soundex', 'like', $metaphone . '%');
        });

        $result = $this->runSql($sql, self::FIELD_FKA, function ($person, &$result) use ($normalized, $metaphone) {
            if (stripos($person->fka_normalized, $normalized) !== false
            || stripos($person->fka_soundex, $metaphone) !== false) {
                $result['fka_match'] = $person->fka;
            }
        });

        $this->addResult($result);
    }

    /**
     * For search for (current) emails.
     */

    public function searchEmail(): void
    {
        $sql = $this->baseSql(false);
        $sql->where('email', $this->query);

        $this->addResult(self::runSql($sql, self::FIELD_EMAIL));
    }

    /**
     * For search for old emails - Ignore statuses filters.
     */

    public function searchOldEmail(): void
    {
        $sql = DB::table('email_history')
            ->select(self::BASE_COLUMNS)
            ->join('person', 'person.id', 'email_history.person_id')
            ->where('email_history.email', $this->query)
            ->orderBy('callsign');

        $this->addResult(self::runSql($sql, self::FIELD_OLD_EMAIL));
    }

    /**
     * Build up a base SQL query.
     *
     * @param bool $addStatuses
     * @return Builder
     */

    public function baseSql(bool $addStatuses = true): Builder
    {
        $sql = DB::table('person')->select(self::BASE_COLUMNS);

        if ($addStatuses) {
            if ($this->statuses && $this->statuses != 'all') {
                $statuses = explode(',', $this->statuses);
                if (stripos($this->statuses, self::CHEETAH_CUB_STATUS) !== false) {
                    $statuses = array_diff($statuses, [self::CHEETAH_CUB_STATUS]);
                    $sql->where(function ($w) use ($statuses) {
                        if (!empty($statuses)) {
                            $w->whereIn('status', $statuses);
                        }
                        $w->orWhereRaw("EXISTS (SELECT 1 FROM slot JOIN person_slot ON person_slot.person_id=person.id WHERE begins_year=? AND position_id=? AND person_slot.slot_id=slot.id LIMIT 1)",
                            [current_year(), Position::CHEETAH_CUB]);
                    });
                } else {
                    $sql->whereIn('status', $statuses);
                }
            }

            if ($this->excludeStatuses) {
                $sql->whereNotIn('status', explode(',', $this->excludeStatuses));
            }
        }

        return $sql;
    }

    /**
     * Run the built-up query.
     *
     * @param Builder $sql
     * @param string $field
     * @param Closure|null $callback
     * @return array
     */

    public function runSql(Builder $sql, string $field, ?Closure $callback = null): array
    {
        $total = $sql->count();
        if ($total) {
            $sql->limit($this->limit);
            $sql->offset($this->offset);
            $rows = $sql->get()->toArray();
        } else {
            $rows = [];
        }

        return [
            'field' => $field,
            'total' => $total,
            'people' => array_map(fn($p) => self::buildPerson($p, $callback), $rows)
        ];
    }

    /**
     * Add the result to return. Skip if no records were found.
     *
     * @param array $result
     * @param array|null $additional
     * @return void
     */

    public function addResult(array $result, ?array $additional = null): void
    {
        if (!$result['total']) {
            return;
        }

        if ($additional) {
            $result = array_merge($result, $additional);
        }

        $this->results[] = $result;
    }

    public static function buildPerson($person, $callback): array
    {
        $result = [
            'id' => $person->id,
            'callsign' => $person->callsign,
            'status' => $person->status,
            'first_name' => $person->first_name,
            'preferred_name' => $person->preferred_name,
            'last_name' => $person->last_name,
        ];

        if ($callback) {
            $callback($person, $result);
        }

        return $result;
    }
}
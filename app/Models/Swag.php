<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Swag extends ApiModel
{
    protected $table = 'swag';
    protected $auditModel = true;
    public $timestamps = true;

    const TYPE_DEPT_PATCH = 'dept-patch';
    const TYPE_DEPT_PIN = 'dept-pin';
    const TYPE_DEPT_SHIRT = 'dept-shirt';
    const TYPE_ORG_PIN = 'org-pin';
    const TYPE_OTHER = 'other';

    const SHIRT_T_SHIRT = 't-shirt';
    const SHIRT_LONG_SLEEVE = 'long-sleeve';

    const SHIRT_SORT_WEIGHTS = [
        'xs' => 1,
        's' => 2,
        'm' => 3,
        'l' => 4,
        'xl' => 5,
        'xxl' => 6,
        '2xl' => 6,
        '3xl' => 7,
        '4xl' => 8,
        '5xl' => 9,
    ];

    const SHIRT_TYPE_SORT_WEIGHT = [
        self::SHIRT_T_SHIRT => 1,
        self::SHIRT_LONG_SLEEVE => 2,
    ];

    protected $rules = [
        'active' => 'required|boolean',
        'description' => 'sometimes|string',
        'title' => 'required|string',
        'type' => 'required|string',
        'shirt_type' => 'sometimes|string',
    ];

    protected $fillable = [
        'active',
        'description',
        'shirt_type',
        'title',
        'type',
    ];

    public function person_swag(): HasMany
    {
        return $this->hasMany(PersonSwag::class);
    }

    /**
     * Find all the swag.
     *
     * @param array $query
     * @return Collection
     */

    public static function findForQuery(array $query): Collection
    {
        $type = $query['type'] ?? null;

        $sql = self::query();
        if ($type) {
            $sql->where('type', $type);
        }

        if (isset($query['active'])) {
            $sql->where('active', $query['active']);
        }

        return self::sortShirtSizes($sql->get());
    }

    /**
     * Find all the department shirts
     *
     * @return array
     */

    public static function retrieveShirts(): array
    {
        $rows = self::findForQuery(['type' => self::TYPE_DEPT_SHIRT]);
        $shirts = [];

        foreach ($rows as $row) {
            $shirts[] = [
                'id' => $row->id,
                'title' => $row->title,
                'type' => $row->type,
                'shirt_type' => $row->shirt_type,
                'active' => $row->active,
            ];
        }

        return $shirts;
    }

    /**
     * Set the shirt type column
     */

    public function setShirtTypeAttribute($value)
    {
        $this->attributes['shirt_type'] = empty($value) ? '' : $value;
    }

    public static function sortShirtSizes($rows)
    {
        return $rows->sort(function ($a, $b) {
            $aTitle = $a->title;
            $bTitle = $b->title;
            if ($a->type != self::TYPE_DEPT_SHIRT || $b->type != self::TYPE_DEPT_SHIRT) {
                return strnatcmp($aTitle, $bTitle);
            }
            $aType = self::SHIRT_TYPE_SORT_WEIGHT[$a->shirt_type] ?? 3;
            $bType = self::SHIRT_TYPE_SORT_WEIGHT[$b->shirt_type] ?? 3;
            if ($aType != $bType) {
                return $aType > $bType ? 1 : -1;
            }
            list ($aTitle, $aWeight) = self::shirtSortWeight($aTitle);
            list ($bTitle, $bWeight) = self::shirtSortWeight($bTitle);
            $cmp = strcasecmp($aTitle, $bTitle);
            if ($cmp) {
                return $cmp;
            }
            if ($aWeight == $bWeight) {
                return 0;
            }
            return $aWeight > $bWeight ? 1 : -1;
        })->values();
    }

    public static function shirtSortWeight($title): array
    {
        if (preg_match("/^(.+)\s+([\dlmsx\- ]+)$/i", $title, $matches) === false) {
            return [$title, 99];
        }

        $size = strtolower(preg_replace("/-/", '', $matches[2]));
        return [$matches[1], self::SHIRT_SORT_WEIGHTS[$size] ?? 99];
    }
}

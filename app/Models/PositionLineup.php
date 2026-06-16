<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;

class PositionLineup extends ApiModel
{
    use HasFactory;

    protected $table = 'position_lineup';
    public $timestamps = true;

    protected $fillable = [
        'description',
        'position_ids'
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected $rules = [
        'description' => 'required|string',
        'position_ids' => 'sometimes|array',
        'position_ids.*' => 'sometimes|integer|exists:position,id'
    ];

    public ?array $position_ids = null;

    public $hidden = [
        'members'
    ];

    public function positions(): hasManyThrough
    {
        return $this->hasManyThrough(
            Position::class,
            PositionLineupMember::class,
            'position_lineup_id', 'id', 'id', 'position_id'
        )->select('position.id', 'position.title', 'position.active')
            ->orderBy('position.title');
    }

    public function members(): HasMany
    {
        return $this->hasMany(PositionLineupMember::class);
    }

    public static function boot(): void
    {
        parent::boot();

        self::deleted(function ($model) {
            DB::table('position_lineup_member')->where('position_lineup_id', $model->id)->delete();
        });

        self::saved(function ($model) {
            if (is_array($model->position_ids)) {
                // Update Position membership
                PositionLineupMember::updateMembership($model->id, $model->position_ids);
            }
        });
    }

    public static function findForQuery(array $query): Collection
    {
        $positionId = $query['position_id'] ?? null;

        $sql = self::with('positions');

        if ($positionId) {
            $sql->select('position_lineup.*')
                ->join('position_lineup_member', 'position_lineup.id', 'position_lineup_member.position_lineup_id')
                ->where('position_lineup_member.position_id', $positionId);
        }

        return $sql->orderBy('position_lineup.description')->get();
    }

    /**
     * Retrieve associated positions for the given position.
     *
     * @param int $positionId
     * @return \Illuminate\Support\Collection|null
     */

    public static function retrieveAssociatedPositions(int $positionId): ?\Illuminate\Support\Collection
    {
        $lineupId = DB::table('position_lineup_member')
            ->where('position_id', $positionId)
            ->value('position_lineup_id');

        if (!$lineupId) {
            return null;
        }

        $rows = DB::table('position_lineup_member')
            ->select('position.id', 'position.title')
            ->join('position', 'position.id', 'position_lineup_member.position_id')
            ->where('position_lineup_id', $lineupId)
            ->where('position_id', '!=', $positionId)
            ->orderBy('position.title')
            ->get();

        return $rows->isNotEmpty() ? $rows : null;
    }

    /**
     * Load up the positions.
     *
     * @return void
     */

    public function loadPositionIds(): void
    {
        $this->position_ids = $this->members->pluck('position_id')->toArray();
        $this->append('position_ids');
    }

    /**
     * Get and set the pseudo position_ids field.
     *
     * This attribute is not a real database column; it is backed by the public
     * $position_ids property. The setter diverts mass-assigned values onto that
     * property (returning an empty array so nothing is written to the attribute
     * bag, which would break save() since there is no position_ids column). The
     * getter resolves the appended attribute during serialization and must read
     * the live property value, so object caching is disabled.
     */

    protected function positionIds(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->position_ids,
            set: function ($value): array {
                $this->position_ids = $value;

                return [];
            },
        )->withoutObjectCaching();
    }
}

<?php

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Collection;

class PositionLineupMember extends ApiModel
{
    use HasCompositePrimaryKey;
    use HasFactory;

    protected $table = 'position_lineup_member';
    protected $primaryKey = ['position_id', 'position_lineup_id'];
    protected $increments = false;

    public function position_lineup(): BelongsTo
    {
        return $this->belongsTo(PositionLineup::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public static function add(int $lineupId, int $positionId): void
    {
        self::insertOrIgnore([
            'position_lineup_id' => $lineupId,
            'position_id' => $positionId,
            'created_at' => now()
        ]);
    }

    public static function remove(int $lineupId, int $positionId): void
    {
        self::where([
            'position_lineup_id' => $lineupId,
            'position_id' => $positionId,
        ])->delete();
    }

    public static function findAllForLineup(int $lineupId): Collection
    {
        return self::where('position_lineup_id', $lineupId)->get();
    }

    /**
     * Update a position lineup's membership
     *
     * @param $lineupId
     * @param $positionIds
     */

    public static function updateMembership($lineupId, $positionIds): void
    {
        $existingLineup = self::findAllForLineup($lineupId);
        $existingById = $existingLineup->keyBy('position_id');

        // Find the ids to add
        foreach ($positionIds as $id) {
            if (!$existingById->has($id)) {
                self::add($lineupId, $id);
            }
        }
        // Find the ids to delete
        foreach ($existingLineup as $existing) {
            if (!in_array($existing->position_id, $positionIds)) {
                self::remove($lineupId, $existing->position_id);
            }
        }
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PersonPosition extends ApiModel
{
    protected $table = 'person_position';

    protected $fillable = [
        'person_id',
        'position_id',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Check to see if the person holds a position or positions.
     *
     * @param $personId
     * @param $positionId
     * @return bool
     */

    public static function havePosition($personId, $positionId): bool
    {
        if (!is_array($positionId)) {
            $positionId = [$positionId];
        }

        return DB::table('person_position')
            ->where('person_id', $personId)
            ->whereIn('position_id', $positionId)
            ->exists();
    }

    /**
     * Find all the Training positions (with the work positions that require passing the
     * training position) for a given person.
     *
     * @param $personId int
     * @param bool $excludePotentials
     * @return Position[]|Collection
     */

    public static function findTrainingPositions(int $personId, bool $excludePotentials = false): Collection|array
    {
        return Position::where('type', Position::TYPE_TRAINING)
            ->join('person_position', 'person_position.position_id', 'position.id')
            ->where('person_position.person_id', $personId)
            ->where(function ($q) use ($excludePotentials, $personId) {
                $q->where('position.id', Position::TRAINING);
                $q->orWhere(function ($q) use ($excludePotentials, $personId) {
                    $q->whereRaw('EXISTS (SELECT 1 FROM position tp WHERE tp.training_position_id=position.id LIMIT 1)');
                    if ($excludePotentials) {
                        $q->whereRaw('EXISTS (SELECT 1 FROM person_position pp JOIN position tp ON pp.position_id=tp.id WHERE pp.person_id=? AND tp.training_position_id=position.id LIMIT 1)', [$personId]);
                    }
                });
            })->orderBy('title')
            ->with([
                'training_positions' => function ($q) use ($personId) {
                    $q->whereRaw('id IN (SELECT position_id FROM person_position WHERE person_position.person_id=?)', [$personId]);
                }])
            ->get()
            ->sortBy('title')
            ->values();
    }

    /**
     * Find folks who are granted a specific position
     *
     * @param int $positionId
     * @return array
     */

    public static function retrieveGrants(int $positionId): array
    {
        return DB::table('person_position')
            ->select('person.id', 'person.callsign')
            ->join('person', 'person_position.person_id', 'person.id')
            ->where('person_position.position_id', $positionId)
            ->orderBy('person.callsign')
            ->get()
            ->toArray();
    }

    /**
     * Find all held positions for a person
     *
     * @param int $personId
     * @param false $includeMentee include Mentee positions even if the person no longer holds them
     * @return mixed
     */

    public static function findForPerson(int $personId, bool $includeMentee = false): mixed
    {
        $rows = DB::table('person_position')
            ->select(
                'position.id',
                'position.title',
                'position.training_position_id',
                'position.active',
                'position.type',
                'position.all_rangers',
                'position.team_id',
                'position.no_training_required'
            )->join('position', 'position.id', 'person_position.position_id')
            ->where('person_id', $personId)
            ->orderBy('position.title')
            ->get();

        if ($includeMentee) {
            // Find mentee and alpha positions
            $sql = Position::select('id', 'title', 'training_position_id', 'active', 'type', 'all_rangers', 'team_id', 'no_training_required')
                ->where('title', 'like', '%mentee%');

            if (Timesheet::hasAlphaEntry($personId)) {
                $sql->orWhere('id', Position::ALPHA);
            }

            $other = $sql->get();
            $rows = $rows->merge($other);
        }

        return $rows->unique('id')->sortBy('title')->values();
    }

    /**
     * Remove all positions from a person in response to status change, add back
     * the default roles if requested.
     *
     * @param int $personId person id to change the roles
     * @param string $reason reason for reset
     * @param string $action
     */

    public static function resetPositions(int $personId, string $reason, string $action)
    {
        $removeIds = self::where('person_id', $personId)->pluck('position_id')->toArray();

        if ($action == Person::ADD_NEW_USER) {
            $addIds = [];
            $ids = Position::where('active', true)->where('new_user_eligible', true)->pluck('id')->toArray();
            foreach ($ids as $positionId) {
                $key = array_search($positionId, $removeIds);
                if ($key !== false) {
                    unset($removeIds[$key]);
                } else {
                    $addIds[] = $positionId;
                }
            }
            self::addIdsToPerson($personId, $addIds, $reason);
        }

        self::removeIdsFromPerson($personId, $removeIds, $reason);
    }

    /*
     * Add positions to a person. Log the action.
     *
     * @param int $personId person to add
     * @param array $ids position ids to add
     * @param string $message reason for addition
     */

    public static function addIdsToPerson(int $personId, $ids, $message)
    {
        $addIds = [];
        foreach ($ids as $id) {
            // Don't worry if there is a duplicate record.
            if (DB::table('person_position')->insertOrIgnore(['person_id' => $personId, 'position_id' => $id]) == 1) {
                $addIds[] = $id;
            }
        }

        if (!empty($addIds)) {
            $ids = array_values($addIds);
            ActionLog::record(Auth::user(), 'person-position-add', $message, ['position_ids' => $ids], $personId);
            foreach ($ids as $id) {
                PersonPositionLog::addPerson($id, $personId);
            }
        }

        PersonRole::clearCache($personId);
    }


    /**
     * Remove positions from a person. Log the action.
     *
     * @param int $personId person to remove
     * @param array $ids position ids to remove
     * @param string $message reason for removal
     */

    public static function removeIdsFromPerson(int $personId, array $ids, string $message)
    {
        if (empty($ids)) {
            return;
        }

        $existingIds = DB::table('person_position')
            ->where('person_id', $personId)
            ->whereIn('position_id', $ids)
            ->pluck('position_id')
            ->toArray();

        if (!empty($existingIds)) {
            DB::table('person_position')
                ->where('person_id', $personId)
                ->whereIn('position_id', $existingIds)
                ->delete();

            ActionLog::record(Auth::user(), 'person-position-remove', $message, ['position_ids' => $existingIds], $personId);
            foreach ($existingIds as $id) {
                PersonPositionLog::removePerson($id, $personId);
            }
        }

        PersonRole::clearCache($personId);
    }

    /**
     * Log changes to person_position
     *
     * @param int $personId person idea
     * @param int $id position id to log
     * @param string $action action taken - usually, 'add' or 'remove'
     * @param string|null $reason optional reason for action ('schedule add', 'trainer removed', etc.)
     */

    public static function log(int $personId, int $id, string $action, string|null $reason = null)
    {
        ActionLog::record(Auth::user(), 'person-position-' . $action, $reason, ['position_id' => $id], $personId);
    }
}

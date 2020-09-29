<?php

namespace App\Models;

use App\Models\Person;
use App\Models\ApiModel;
use App\Models\Position;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PersonPosition extends ApiModel
{
    protected $table = 'person_position';

    protected $fillable = [
        'person_id',
        'position_id'
    ];

    protected $casts = [
        'active' => 'bool'
    ];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function position()
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
    public static function havePosition($personId, $positionId)
    {
        $sql = self::where('person_id', $personId);
        if (is_array($positionId)) {
            $sql = $sql->whereIn('position_id', $positionId);
        } else {
            $sql = $sql->where('position_id', $positionId);
        }

        return $sql->exists();
    }

    /**
     * Find all the Training positions (with the work positions that require passing the
     * training position) for a given person.
     *
     * @param $personId int
     * @return Position[]|Collection
     */

    public static function findTrainingPositions(int $personId, bool $excludePotentials = false)
    {
        $sql = Position::where('type', Position::TYPE_TRAINING)
            ->join('person_position', 'person_position.position_id', 'position.id')
            ->where('person_position.person_id', $personId)
            ->where(function ($q) use ($excludePotentials, $personId) {
                $q->where('position.id', Position::TRAINING);
                $q->orWhere(function($q) use ($excludePotentials, $personId) {
                    $q->whereRaw('EXISTS (SELECT 1 FROM position tp WHERE tp.training_position_id=position.id LIMIT 1)');
                    if ($excludePotentials) {
                        $q->whereRaw('EXISTS (SELECT 1 FROM person_position pp JOIN position tp ON pp.position_id=tp.id WHERE pp.person_id=? AND tp.training_position_id=position.id LIMIT 1)', [$personId]);
                    }
                });
            })
            ->orderBy('title')
            ->with(['training_positions' => function ($q) use ($personId) {
                $q->whereRaw('id IN (SELECT position_id FROM person_position WHERE person_position.person_id=?)', [$personId]);
            }]);

        return $sql->get();
    }

    /**
     * Find all the positions held by a person
     *
     * @param $personId
     * @param false $includeMentee
     * @return PersonPosition[]|Collection
     */

    public static function findForPerson(int $personId, bool $includeMentee = false)
    {
        $rows = self::select('position.id', 'position.title', 'position.training_position_id', 'position.active')
            ->join('position', 'position.id', '=', 'person_position.position_id')
            ->where('person_id', $personId)
            ->orderBy('position.title')
            ->get();

        if (!$includeMentee) {
            return $rows;
        }

        // Find mentee and alpha positions
        $sql = Position::select('position.id', 'position.title', 'position.training_position_id')
            ->where('title', 'like', '%mentee%');

        if (Timesheet::hasAlphaEntry($personId)) {
            $sql->orWhere('id', Position::ALPHA);
        }

        $other = $sql->get();

        return $rows->merge($other)->unique('id')->sortBy('title')->values();
    }

    /**
     * Remove all positions from a person in response to status change, add back
     * the default roles if requested.
     *
     * @param integer $personId person id to change the roles
     * @param string $reason reason for reset
     * @param bool $resetToDefaultPositions if true, add the default positions
     */

    public static function resetPositions($personId, $reason, $action)
    {
        $removeIds = self::where('person_id', $personId)->pluck('position_id')->toArray();

        if ($action == Person::ADD_NEW_USER) {
            $addIds = [];
            $ids = Position::where('new_user_eligible', true)->pluck('id')->toArray();
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
     * @param integer $personid person to remove
     * @param array $ids position ids to remove
     * @param string $message reason for addition
     */

    public static function addIdsToPerson($personId, $ids, $message)
    {
        $addIds = [];
        foreach ($ids as $id) {
            // Don't worry if there is a duplicate record.
            if (DB::affectingStatement("INSERT IGNORE INTO person_position SET person_id=?, position_id=?", [$personId, $id]) == 1) {
                $addIds[] = $id;
            }
        }

        if (!empty($addIds)) {
            ActionLog::record(Auth::user(), 'person-position-add', $message, ['position_ids' => array_values($addIds)], $personId);
        }
    }


    /**
     * Remove positions from a person. Log the action.
     *
     * @param integer $personid person to remove
     * @param array $ids position ids to remove
     * @param string $message reason for removal
     */

    public static function removeIdsFromPerson($personId, $ids, $message)
    {
        if (empty($ids)) {
            return;
        }

        DB::table('person_position')
            ->where('person_id', $personId)
            ->whereIn('position_id', $ids)
            ->delete();


        ActionLog::record(Auth::user(), 'person-position-remove', $message, ['position_ids' => array_values($ids)], $personId);
    }

    /**
     * Log changes to person_position
     *
     * @param integer $personId person idea
     * @param integer $id position id to log
     * @param string $action action taken - usually, 'add' or 'remove'
     * @param string $reason optional reason for action ('schedule add', 'trainer removed', etc.)
     */

    public static function log($personId, $id, $action, $reason = null)
    {
        ActionLog::record(Auth::user(), 'person-position-' . $action, $reason, ['position_id' => $id], $personId);
    }
}

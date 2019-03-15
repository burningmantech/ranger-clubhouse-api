<?php

namespace App\Models;

use App\Models\Person;

use Illuminate\Database\Eloquent\Model;

class ActionLog extends Model
{
    protected $table = 'action_logs';

    protected $guarded = [ ];

    // created_at is handled by the database itself
    public $timestamps = false;

    const PAGE_SIZE_DEFAULT = 50;

    public function person() {
        return $this->belongsTo(Person::class);
    }

    public function target_person() {
        return $this->belongsTo(Person::class);
    }

    public static function findForQuery($query) {
        $personId = $query['person_id'] ?? null;
        $page = $query['page'] ?? 1;
        $pageSize = $query['page_size'] ?? self::PAGE_SIZE_DEFAULT;
        $events = $query['events'] ?? [ ];
        $sort = $query['sort'] ?? 'desc';
        $startTime = $query['start_time'] ?? null;
        $endTime = $query['end_time'] ?? null;

        $sql = self::query();

        if ($personId) {
            $sql->where('person_id', $personId)
                ->orWhere('target_person_id', $personId);
        }

        if (!empty($events)) {
            $exactEvents = [];
            $likeEvents = [];

            foreach ($events as $event) {
                if (strpos($event, '%') === false) {
                    $exactEvents[] = $event;
                } else {
                    $likeEvents[] = $event;
                }
            }

            $sql->orWhere(function ($query) use ($exactEvents, $likeEvents) {
                if (!empty($exactEvents)) {
                    $query->orWhereIn('event', $exactEvents);
                }

                if (!empty($likeEvents)) {
                    foreach ($likeEvents as $event) {
                        $query->orWhere('event', 'LIKE', $event);
                    }
                }
            });
        }

        if ($startTime) {
            $sql->where('created_at', '>=', $startTime);
        }

        if ($endTime) {
            $sql->where('created_at', '<=', $endTime);
        }

        // How many total for the query
        $total = $sql->count();

        if (!$total) {
            // Nada.. don't bother
            return [ 'logs' => [ ], 'page' => 0, 'total' => 0, 'total_pages' => 0 ];
        }

        // Results sort 'asc' or 'desc'
        $sql->orderBy('created_at', ($sort == 'asc' ? 'asc' : 'desc'));

        // Figure out pagination
        $page = $page - 1;
        if ($page < 0) {
            $page = 0;
        }

        $sql->offset($page * $pageSize)->limit($pageSize);

        // .. and go get it!
        $rows = $sql->with([ 'person:id,callsign', 'target_person:id,callsign'])->get();

        foreach ($rows as $row) {
            if (empty($row->data)) {
                continue;
            }

            $data = json_decode($row->data);
            if (!$data) {
                continue;
            }

            if (isset($data->slot_id)) {
                $row->slot = Slot::where('id', $data->slot_id)->with('position:id,title')->first();
            }

            if (isset($data->position_ids)) {
                $row->positions = Position::whereIn('id', $data->position_ids)->orderBy('title')->get([ 'id', 'title' ]);
            }

            if (isset($data->role_ids)) {
                $row->roles = Role::whereIn('id', $data->role_ids)->orderBy('title')->get([ 'id', 'title' ]);
            }
        }

        return [
            'logs'        => $rows,
            'total'       => $total,
            'total_pages' => (int) (($total + ($pageSize - 1))/$pageSize),
            'page_size'   => $pageSize,
            'page'        => $page + 1,
         ];

    }

    public static function record($person, $event, $message, $data=null, $targetPersonId=null) {
        $log = new ActionLog;
        $log->event = $event;
        $log->person_id = $person ? $person->id : null;
        $log->message = $message;
        $log->target_person_id = $targetPersonId;

        if ($data) {
            $log->data = json_encode($data);
        }

        $log->save();
    }
}

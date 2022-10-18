<?php

namespace App\Models;

use Carbon\Carbon;
use DateTime;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionLog extends Model
{
    protected $table = 'action_logs';

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'data' => 'array'
    ];

    // created_at is handled by the database itself
    public $timestamps = false;

    const PAGE_SIZE_DEFAULT = 50;

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function target_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Retrieve action log records based on the given query criteria.
     *
     * @param array $query
     * @param bool $redactData - true if the data column is to be redacted.
     * @return array
     */
    public static function findForQuery(array $query, bool $redactData): array
    {
        $personId = $query['person_id'] ?? null;
        $page = (int)($query['page'] ?? 1);
        $pageSize = (int) ($query['page_size'] ?? self::PAGE_SIZE_DEFAULT);
        $events = $query['events'] ?? [];
        $sort = $query['sort'] ?? 'desc';
        $startTime = $query['start_time'] ?? null;
        $endTime = $query['end_time'] ?? null;
        $lastDay = $query['lastday'] ?? false;
        $message = $query['message'] ?? null;

        $sql = self::query();

        if ($personId) {
            $sql->where(function ($q) use ($personId) {
                $q->where('person_id', $personId)
                    ->orWhere('target_person_id', $personId);
            });
        }

        if (!empty($events)) {
            $exactEvents = [];
            $likeEvents = [];

            foreach ($events as $event) {
                if (str_contains($event, '%')) {
                    $likeEvents[] = $event;
                } else {
                    $exactEvents[] = $event;
                }
            }

            $sql->where(function ($query) use ($exactEvents, $likeEvents) {
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

        if ($lastDay) {
            $sql->whereRaw('created_at >= ?', [now()->subHours(24)]);
        }

        if ($message) {
            if (str_contains($message, '%')) {
                $sql->where('message', 'like', $message);
            } else {
                $sql->where('message', $message);
            }
        }

        // How many total for the query
        $total = $sql->count();

        if (!$total) {
            // Nada.. don't bother
            return ['action_logs' => [], 'meta' => ['page' => 0, 'total' => 0, 'total_pages' => 0]];
        }

        // Results sort 'asc' or 'desc'
        $sortOrder = ($sort == 'asc' ? 'asc' : 'desc');
        $sql->orderBy('created_at', $sortOrder);
        $sql->orderBy('id', $sortOrder);

        // Figure out pagination
        $page = $page - 1;
        if ($page < 0) {
            $page = 0;
        }

        if ($pageSize <= 0) {
            $pageSize = self::PAGE_SIZE_DEFAULT;
        }

        $sql->offset($page * $pageSize)->limit($pageSize);

        // .. and go get it!
        $rows = $sql->with(['person:id,callsign', 'target_person:id,callsign'])->get();

        foreach ($rows as $row) {
            $data = $row->data;

            if (empty($row->data)) {
                continue;
            }

            if (isset($data['slot_id'])) {
                $row->slot = Slot::where('id', $data['slot_id'])->with('position:id,title')->first();
            }

            if (isset($data['enrolled_slot_ids']) && is_array($data['enrolled_slot_ids'])) {
                $row->enrolled_slots = Slot::whereIn('id', $data['enrolled_slot_ids'])->with('position:id,title')->first();
            }

            if (isset($data['position_ids']) && is_array($data['position_ids'])) {
                $row->positions = Position::whereIn('id', $data['position_ids'])->orderBy('title')->get(['id', 'title']);
            }

            if (isset($data['position_id'])) {
                $row->position = Position::where('id', $data['position_id'])->first();
            }

            if (isset($data['role_ids']) && is_array($data['role_ids'])) {
                $row->roles = Role::whereIn('id', array_values($data['role_ids']))->orderBy('title')->get(['id', 'title']);
            }

            if ($redactData) {
                $row->data = null;
            }
        }

        return [
            'action_logs' => $rows,
            'meta' => [
                'total' => $total,
                'total_pages' => (int)(($total + ($pageSize - 1)) / $pageSize),
                'page_size' => $pageSize,
                'page' => $page + 1,
            ]
        ];
    }

    /**
     * Record a Clubhouse event
     *
     * @param $user - user performing the action
     * @param $event - event that happened.
     * @param $message - optional message/reason
     * @param $data - relevant data to log
     * @param $targetPersonId - the target id who the action was taken against
     * @return void
     */

    public static function record($user, $event, $message, $data = null, $targetPersonId = null): void
    {
        $log = new ActionLog;
        $log->event = $event;
        $log->person_id = $user?->id;
        $log->message = $message ?? '';
        $log->target_person_id = $targetPersonId;

        if ($data) {
            $log->data = $data;
        }

        // We want the real time, not the simulated GHD time returned by now().
        $log->created_at = new Carbon(new DateTime);
        $log->save();
    }

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param DateTimeInterface $date
     * @return string
     */

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}

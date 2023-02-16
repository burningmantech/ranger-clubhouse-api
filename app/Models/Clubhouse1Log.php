<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Clubhouse1Log extends Model
{
    protected $table = 'log';

    const PAGE_SIZE_DEFAULT = 50;

    protected $casts = [
        'occurred' => 'datetime',
    ];

    public function user_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function current_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public static function findForQuery(array $query): array
    {
        $personId = $query['person_id'] ?? null;
        $page = (int)($query['page'] ?? 1);
        $pageSize = (int)($query['page_size'] ?? self::PAGE_SIZE_DEFAULT);
        $events = $query['events'] ?? [];
        $sort = $query['sort'] ?? 'desc';
        $startTime = $query['start_time'] ?? null;
        $endTime = $query['end_time'] ?? null;
        $lastDay = $query['lastday'] ?? false;
        $eventText = $query['event_text'] ?? null;

        $sql = self::query();

        if ($personId) {
            $sql->where(function ($q) use ($personId) {
                $q->where('current_person_id', $personId)
                    ->orWhere('user_person_id', $personId);
            });
        }

        if (!empty($events)) {
            $events = explode(',', $events);
            $sql->where(function ($query) use ($events) {
                foreach ($events as $event) {
                    $query->orWhere('event', 'LIKE', $event . '%');
                }
            });
        }

        if (!empty($eventText)) {
            $sql->where('event', 'LIKE', '%' . $eventText . '%');
        }

        if ($startTime) {
            $sql->where('occurred', '>=', $startTime);
        }

        if ($endTime) {
            $sql->where('occurred', '<=', $endTime);
        }

        if ($lastDay) {
            $sql->whereRaw('occurred >= DATE_SUB(?, INTERVAL 1 DAY)', [now()]);
        }

        // How many total for the query
        $total = $sql->count();

        if (!$total) {
            // Nada.. don't bother
            return ['logs' => [], 'page' => 0, 'total' => 0, 'total_pages' => 0];
        }

        // Results sort 'asc' or 'desc'
        $sql->orderBy('occurred', ($sort == 'asc' ? 'asc' : 'desc'));

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
        $rows = $sql->with(['current_person:id,callsign', 'user_person:id,callsign'])->get();

        return [
            'logs' => $rows,
            'total' => $total,
            'total_pages' => (int)(($total + ($pageSize - 1)) / $pageSize),
            'page_size' => $pageSize,
            'page' => $page + 1,
        ];
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

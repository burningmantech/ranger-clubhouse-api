<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailLog extends ApiModel
{
    use HasFactory;

    protected $table = 'mail_log';
    public $timestamps = true;

    protected $guarded = [];    // Records are not directly created by users.

    protected $casts = [
        'created_at' => 'datetime',
    ];

    const PAGE_SIZE_DEFAULT = 50;

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public static function findForQuery($query)
    {
        $personId = $query['person_id'] ?? null;
        $year = $query['year'] ?? null;
        $page = $query['page'] ?? 1;
        $pageSize = $query['page_size'] ?? self::PAGE_SIZE_DEFAULT;

        $sql = self::query();

        if ($personId) {
            $sql->where(function ($q) use($personId) {
                $q->where('person_id', $personId);
                $q->orWhere('sender_id', $personId);
            });
        }

        if ($year) {
            $sql->whereYear('created_at', $year);
        }

        // How many total for the query
        $total = $sql->count();

        if (!$total) {
            // Nada.. don't bother
            return [
                'mail_log' => [],
                'meta' => [
                    'page' => 0,
                    'total' => 0,
                    'total_pages' => 0,
                    'page_size' => $pageSize,
                ]
            ];
        }

        // Figure out pagination
        $page = $page - 1;
        if ($page < 0) {
            $page = 0;
        }

        $rows = $sql->with(['person:id,callsign', 'sender:id,callsign', 'broadcast'])
            ->offset($page * $pageSize)
            ->limit($pageSize)
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'mail_log' => $rows,
            'meta' => [
                'total' => $total,
                'total_pages' => (int)(($total + ($pageSize - 1)) / $pageSize),
                'page_size' => $pageSize,
                'page' => $page + 1,
            ]
        ];
    }

    public function setBodyAttribute($value)
    {
        $this->attributes['body'] = !empty($value) ? gzencode($value) : '';
    }

    public function getSubjectAttribute(): string
    {
        if ($this->broadcast_id) {
            return $this->broadcast?->subject ?? '';
        }

        return $this->attributes['subject'];
    }

    public function getBodyAttribute(): string
    {
        if ($this->broadcast_id) {
            $body = $this->broadcast?->email_message ?? '';
        } else {

            $body = $this->attributes['body'];
            if (empty($body)) {
                return '';
            }
            $body = gzdecode($body);
        }

        if (preg_match("/=3d/i", $body)) {
            $body = quoted_printable_decode($body);
        }

        return $body;
    }
}

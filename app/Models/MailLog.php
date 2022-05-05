<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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

    /**
     * Retrieve mail log records based on the given criteria
     *
     * @param $query
     * @return array
     */

    public static function findForQuery($query): array
    {
        $personId = $query['person_id'] ?? null;
        $year = $query['year'] ?? null;
        $page = $query['page'] ?? 1;
        $pageSize = $query['page_size'] ?? self::PAGE_SIZE_DEFAULT;

        $sql = self::query();

        if ($personId) {
            $sql->where(function ($q) use ($personId) {
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

    /**
     * Retrieve the years for a person or the website
     *
     * @param int|null $personId
     * @return array
     */

    public static function retrieveYears(?int $personId): array
    {
        $sql = self::selectRaw("YEAR(created_at) as year")
            ->groupBy("year")
            ->orderBy("year", "asc");

        if ($personId) {
            $sql->where(function ($q) use ($personId) {
                $q->where('person_id', $personId)
                    ->orWhere('sender_id', $personId);
            });
        }

        $years = $sql->pluck('year')->toArray();
        sort($years, SORT_NUMERIC);
        return $years;
    }

    /**
     * Calculate the statistics for either the website or a person.
     *
     * @param ?int $personId
     * @param callable $condCallback
     * @return int
     */

    public static function countStats(?int $personId, callable $condCallback): int
    {
        $sql = DB::table('mail_log');
        if ($personId) {
            $sql->where(function ($q) use ($personId) {
                $q->where('person_id', $personId)
                    ->orWhere('sender_id', $personId);
            });
        }
        $condCallback($sql);
        return $sql->count();
    }

    /**
     * Retrieve the sending stats for the last 24 hours, 7 days, and current year.
     *
     * @param $personId
     * @return int[]
     */

    public static function retrieveStats($personId): array
    {
        return [
            'last_24' => self::countStats($personId, fn($sql) => $sql->where('created_at', '>=', now()->subDay(1))),
            'week' => self::countStats($personId, fn($sql) => $sql->where('created_at', '>=', now()->subDays(7))),
            'year' => self::countStats($personId, fn($sql) => $sql->whereYear('created_at', current_year())),
        ];
    }

    /**
     * Mark any records matching the Message-ID header as having bounced.
     *
     * @param string $messageId
     * @return void
     */

    public static function markAsBounced(string $messageId)
    {
        DB::table('mail_log')->where('message_id', $messageId)->update(['did_bounce' => true]);
    }

    /**
     * Mark any records matching the Message-ID header as having a complaint
     * (aka determined to be spam by the recipient's mail server).
     *
     * @param string $messageId
     * @return void
     */

    public static function markAsComplaint(string $messageId)
    {
        DB::table('mail_log')->where('message_id', $messageId)->update(['did_complain' => true]);
    }

    /**
     * Gzip up the body attribute
     *
     * @param $value
     * @return void
     */

    public function setBodyAttribute($value)
    {
        $this->attributes['body'] = !empty($value) ? gzencode($value) : '';
    }

    /**
     * Get the subject attribute either from this record or the associated broadcast record
     *
     * @return string
     */

    public function getSubjectAttribute(): string
    {
        if ($this->broadcast_id) {
            return $this->broadcast?->subject ?? '';
        }

        return $this->attributes['subject'];
    }

    /**
     * Get the body attribute. Either return the associated broadcast record's
     * body attribute, or return a gunzip'ed version. Decode the body is the original
     * value was printable-encoded.
     *
     * @return string
     */

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

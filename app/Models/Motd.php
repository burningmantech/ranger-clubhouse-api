<?php

namespace App\Models;

use App\Attributes\NullIfEmptyAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class Motd extends ApiModel
{
    protected $table = 'motd';
    protected bool $auditModel = true;
    public $timestamps = true;

    protected $guarded = [
        'person_id',
        'created_at',
        'updated_at'
    ];

    protected $rules = [
        'subject' => 'required|string',
        'message' => 'required|string',
        'expires_at' => 'required|date',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'expires_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'has_expired',
        'has_read'
    ];


    public static function boot(): void
    {
        parent::boot();

        self::deleted(function ($model) {
            DB::table('person_motd')->where('motd_id', $model->id)->delete();
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public static function findForQuery(array $query): array
    {
        $audience = $query['audience'] ?? 'all';
        $type = $query['type'] ?? null;
        $page = $query['page'] ?? 1;
        $pageSize = $query['page_size'] ?? 20;
        $sort = $query['sort'] ?? 'desc';

        $sql = self::query();

        switch ($audience) {
            case 'auditors':
            case 'pnvs':
            case 'rangers':
                $sql->where('for_' . $audience, 1);
                break;
            case 'all':
                break;
            default:
                throw new InvalidArgumentException("Unknown audience value");
        }

        switch ($type) {
            case 'expired':
                $sql->where('expires_at', '<', now());
                break;
            case 'active':
                $sql->where(function ($q) {
                    $q->where('expires_at', '>', now());
                    $q->orWhereNull('expires_at');
                });
                break;
        }

        $total = $sql->count();
        $page = $page - 1;
        if ($page < 0) {
            $page = 0;
        }

        $sql->offset($page * $pageSize)->limit($pageSize);
        $sql->orderBy('expires_at', ($sort == 'asc' ? 'asc' : 'desc'));

        return [
            'motd' => $sql->with('person:id,callsign')->get(),
            'meta' => [
                'total' => $total,
                'total_pages' => (int)(($total + ($pageSize - 1)) / $pageSize),
                'page_size' => $pageSize,
                'page' => $page + 1,
                'table_count' => self::query()->count()
            ]
        ];

    }

    public static function findForBulletin(int $personId, string $status, array $params): array
    {
        $type = $params['type'] ?? 'all';
        $page = (int)($params['page'] ?? 1);
        $pageSize = (int)($params['page_size'] ?? 20);

        switch ($status) {
            case Person::AUDITOR:
                $audience = 'for_auditors';
                break;
            case Person::PROSPECTIVE:
            case Person::ALPHA:
                $audience = 'for_pnvs';
                break;
            default:
                if (in_array($status, Person::NO_MESSAGES_STATUSES)) {
                    return [
                        'motd' => [],
                        'meta' => [
                            'total' => 0,
                            'total_pages' => 0,
                            'page_size' => $pageSize,
                            'page' => 1,
                        ]
                    ];
                }
                $audience = 'for_rangers';
                break;
        }

        $sql = self::where($audience, 1);
        $sql->leftJoin('person_motd', function ($j) use ($personId) {
            $j->on('person_motd.motd_id', 'motd.id');
            $j->where('person_motd.person_id', $personId);
        });

        switch ($type) {
            case 'expired':
                $sql->where('expires_at', '<', now());
                break;

            case 'unread':
                $sql->where('expires_at', '>', now());
                $sql->whereNull('person_motd.read_at');
                break;

            default:
                $sql->where('expires_at', '>', now());
                break;
        }

        $total = $sql->count();

        $sql->select('motd.*', DB::raw('IFNULL(person_motd.read_at, FALSE) as has_read'));
        // Figure out pagination
        $page = $page - 1;
        if ($page < 0) {
            $page = 0;
        }
        if ($pageSize <= 0) {
            $pageSize = 20;
        }

        $sql->offset($page * $pageSize)->limit($pageSize);

        return [
            'motd' => $sql->orderBy('created_at')->get(),
            'meta' => [
                'total' => $total,
                'total_pages' => (int)(($total + ($pageSize - 1)) / $pageSize),
                'page_size' => $pageSize,
                'page' => $page + 1,
            ]
        ];
    }

    public function expiresAt(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function getHasReadAttribute()
    {
        return (bool)($this->attributes['has_read'] ?? false);
    }

    public function getHasExpiredAttribute()
    {
        return $this->expires_at ? $this->expires_at->lt(now()) : false;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OauthClient extends ApiModel
{
    protected $table = 'oauth_client';
    protected bool $auditModel = true;
    public $timestamps = true;

    // Not directly accessible, allow all fields to be fillable
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime'
        ];
    }

    protected $fillable = [
        'client_id',
        'secret',
        'description',
    ];

    protected $rules = [
        'client_id' => 'required|string',
        'description' => 'required|string',
    ];

    public static function boot(): void
    {
        parent::boot();

        self::deleted(function ($model) {
            OauthCode::where('oauth_client_id', $model->id)->delete();
        });
    }

    public function oauth_codes(): HasMany
    {
        return $this->hasMany(OauthCode::class);
    }

    public static function findAll(): Collection
    {
        return self::orderBy('client_id')->get();
    }

    public static function findForClientId(string $clientId): ?self
    {
        return self::where('client_id', $clientId)->first();
    }

    public function save($options = []): bool
    {
        if ($this->exists) {
            $this->rules['secret'] = 'required|string';
        } else {
            $this->secret = Str::random(32);
        }

        if (!$this->exists || $this->isDirty('client_id')) {
            $this->rules['client_id'] = [
                'required',
                'string',
                Rule::unique('oauth_client')->where(function ($q) {
                    $q->where('client_id', $this->client_id);
                    if ($this->exists) {
                        $q->where('id', '!=', $this->id);
                    }
                })
            ];

        }
        return parent::save($options);
    }
}
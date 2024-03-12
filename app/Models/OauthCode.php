<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OauthCode extends ApiModel
{
    const int EXPIRE_IN_SECONDS = 120;

    protected $table = 'oauth_code';
    protected bool $auditModel = true;

    // Not directly accessible, allow all fields to be fillable
    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime'
    ];

    public static function deleteExpired(): void
    {
        self::where('created_at', '<=', now()->subSeconds(self::EXPIRE_IN_SECONDS))->delete();
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function oauth_client(): BelongsTo
    {
        return $this->belongsTo(OauthClient::class);
    }

    public static function findForClientCode(OauthClient $client, string $code): ?OauthCode
    {
        return self::where('oauth_client_id', $client->id)->where('code', $code)->first();
    }

    public static function createCodeForClient(OauthClient $client, Person $person, string $scope): string
    {
        $oc = new OauthCode([
            'oauth_client_id' => $client->id,
            'person_id' => $person->id,
            'code' => Str::random(64),
            'scope' => $scope,
            'created_at' => now(),
        ]);

        $oc->save();

        return $oc->code;
    }
}
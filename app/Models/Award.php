<?php

namespace App\Models;

use App\Attributes\BlankIfEmptyAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Award extends ApiModel
{
    protected $table = 'award';
    protected bool $auditModel = true;
    public $timestamps = true;

    protected $rules = [
        'description' => 'string|nullable',
        'title' => 'required|string',
    ];

    protected $fillable = [
        'description',
        'title',
    ];

    public static function boot(): void
    {
        parent::boot();

        self::deleted(function ($model) {
            DB::table('person_award')->where('award_id', $model->id)->delete();
        });
    }

    public function person_award(): HasMany
    {
        return $this->hasMany(PersonAward::class);
    }

    public static function findAll()
    {
        return self::get()->sortBy('title', SORT_NATURAL)->values();
    }

    public static function findByTitle(string $title): ?self
    {
        return self::where('title', $title)->first();
    }

    public function description(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }
}

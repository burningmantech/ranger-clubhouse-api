<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Document extends ApiModel
{
    use HasFactory;

    protected $table = 'document';
    protected bool $auditModel = true;
    public $timestamps = true;

    protected $fillable = [
        'tag',
        'description',
        'body'
    ];

    protected $rules = [
        'tag' => 'required|string',
        'description' => 'required|string',
        'body' => 'required|string'
    ];

    public function person_create(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function person_update(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Ensure the document tag is unique when creating/saving.
     *
     * @param $options
     * @return bool
     */

    public function save($options = []): bool
    {
        if (!$this->exists || $this->isDirty('tag')) {
            $this->rules['tag'] = [
                'required',
                'string',
                'unique:document,tag'
            ];
        }

        return parent::save($options);
    }

    /**
     * Find document based on either record id or tag.
     *
     * @param $idOrTag
     * @return Document|null
     */

    public static function findIdOrTag($idOrTag): ?Document
    {
        return self::where('id', $idOrTag)->orWhere('tag', $idOrTag)->first();
    }

    /**
     * Find all the documents
     *
     * @return Collection
     */
    public static function findAll(): Collection
    {
        return self::orderBy('tag')
            ->with(['person_create:id,callsign', 'person_update:id,callsign'])
            ->get();
    }

    /**
     * A does a document tag exist?
     *
     * @param string $tag
     * @return bool
     */

    public static function haveTag(string $tag): bool
    {
        return DB::table('document')->where('tag', $tag)->exists();
    }
}

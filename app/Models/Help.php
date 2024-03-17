<?php

namespace App\Models;

class Help extends ApiModel
{
    protected $table = 'help';
    protected bool $auditModel = true;

    protected $fillable = [
        'slug',
        'body',
        'tags',
        'title'
    ];

    protected $rules = [
        'slug' => 'required|string|max:128',
        'title' => 'required|string|max:128',
        'body' => 'required|string',
        'tags' => 'sometimes|string|max:255|nullable'
    ];

    public static function findAll()
    {
        return self::orderBy('slug')->get();
    }

    public static function findByIdOrSlug($id): ?Help
    {
        if (is_numeric($id)) {
            return self::where('id', $id)->first();
        } else {
            return self::where('slug', $id)->first();
        }
    }

    public static function findByIdOrSlugOrFail($id): Help
    {
        if (is_numeric($id)) {
            return self::where('id', $id)->firstOrFail();
        } else {
            return self::where('slug', $id)->firstOrFail();
        }
    }
}

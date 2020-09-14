<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Models\Person;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends ApiModel
{
    use HasFactory;

    protected $table = 'document';
    protected $auditModel = true;
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

    public function save($options = [])
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

    public static function findIdOrTag($idOrTag)
    {
        return self::where('id', $idOrTag)->orWhere('tag', $idOrTag)->first();
    }

    public function person_create()
    {
        return $this->belongsTo(Person::class);
    }

    public function person_update()
    {
        return $this->belongsTo(Person::class);
    }

    public static function findAll()
    {
        return self::orderBy('tag')
            ->with(['person_create:id,callsign', 'person_update:id,callsign'])
            ->get();
    }
}

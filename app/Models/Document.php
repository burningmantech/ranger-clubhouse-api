<?php

namespace App\Models;

use App\Attributes\NullIfEmptyAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Document extends ApiModel
{
    use HasFactory;

    protected $table = 'document';
    protected bool $auditModel = true;
    public $timestamps = true;

    // Known documents

    const string BEHAVIORAL_STANDARDS_AGREEMENT_TAG = 'behavioral-standards-agreement';
    const string DEPT_NDA_TAG = 'dept-nda';
    const string MOTORPOOL_POLICY_TAG = 'motorpool-policy';
    const string PERSONAL_VEHICLE_AGREEMENT_TAG = 'personal-vehicle-agreement';
    const string RADIO_CHECKOUT_AGREEMENT_TAG = 'radio-checkout-agreement';
    const string SANDMAN_AFFIDAVIT_TAG = 'sandman-affidavit';
    const string MVR_FORM_INSTRUCTIONS_TAG = 'mvr-form-instructions';

    protected $fillable = [
        'body',
        'description',
        'refresh_time',
        'tag',
    ];

    protected $rules = [
        'tag' => 'required|string',
        'description' => 'required|string',
        'body' => 'required|string',
        'refresh_time' => 'sometimes|nullable|integer',
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
        if (stripos($this->tag, ' ') !== false) {
            $this->addError('tag', 'Tag may not contain spaces');
            return false;
        }

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

    public static function findIdOrTagOrFail($idOrTag): ?Document
    {
        return self::where('id', $idOrTag)->orWhere('tag', $idOrTag)->firstOrFail();
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

    /**
     * Obtain the document contents (aka body) by tag
     *
     * @param string $tag
     * @return string
     */

    public static function contentsByTag(string $tag) : string
    {
        return self::where('tag', $tag)->first()?->body ?? '';
    }

    public function refreshTime(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }
}

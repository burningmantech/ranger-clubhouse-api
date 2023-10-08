<?php

namespace App\Models;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BmidExport extends ApiModel
{
    use HasFactory;

    protected $table = 'bmid_export';

    const STORAGE_DIR = 'exports/';

    protected $casts = [
        'person_ids' => 'array',
        'created_at' => 'datetime'
    ];

    protected $appends = [
        'filename_url'
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Find all exports for a given year
     *
     * @param int $year
     * @return Collection
     */

    public static function findAllForYear(int $year): Collection
    {
        return self::whereYear('created_at', $year)
            ->with('person:id,callsign')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Store/upload the export to storage
     *
     * @param $filename - filename to store as
     * @param $contents - contents of file.
     * @return bool - true if successful
     */

    public function storeExport($filename, $contents): bool
    {
        $this->filename = $filename;
        return self::storage()->put(self::storagePath($filename), $contents);
    }

    /**
     * Delete the export from storage
     */

    public function deleteExport(): void
    {
        self::storage()->delete(self::storagePath($this->filename));
    }

    /**
     * Obtain the photo storage object
     *
     * @return Filesystem
     */

    public static function storage(): Filesystem
    {
        return Storage::disk(config('clubhouse.BmidExportStorage', true));
    }

    /**
     * Create a (local filesystem not url) path to where the export is stored
     *
     * @param string $filename
     * @return string
     */

    public static function storagePath(string $filename): string
    {
        return self::STORAGE_DIR . $filename;
    }

    public function getFilenameUrlAttribute(): string
    {
        return self::storage()->url(self::storagePath($this->filename));
    }
}

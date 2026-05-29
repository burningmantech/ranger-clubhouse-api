<?php

namespace App\Lib\BulkUpload;

use App\Lib\BulkUploader;
use App\Models\Person;

/**
 * Typed per-row record used by every bulk upload handler. Replaces the
 * ad-hoc (object)[...] casts the dispatcher used to build.
 */
class Record
{
    public ?Person $person = null;
    public ?string $status = null;
    public ?string $details = null;
    /** @var array<int, mixed>|null */
    public ?array $changes = null;

    /**
     * @param string $callsign trimmed, non-empty
     * @param list<string> $data remaining comma-separated fields (un-trimmed)
     */
    public function __construct(
        public readonly string $callsign,
        public readonly array $data,
    ) {
    }

    public function fail(string $details): void
    {
        $this->status = BulkUploader::STATUS_FAILED;
        $this->details = $details;
    }

    /**
     * @param array<int, mixed>|null $changes
     */
    public function succeed(?array $changes = null): void
    {
        $this->status = BulkUploader::STATUS_SUCCESS;
        if ($changes !== null) {
            $this->changes = $changes;
        }
    }

    /**
     * @param array<int, mixed>|null $changes
     */
    public function warn(string $details, ?array $changes = null): void
    {
        $this->status = BulkUploader::STATUS_WARNING;
        $this->details = $details;
        if ($changes !== null) {
            $this->changes = $changes;
        }
    }
}

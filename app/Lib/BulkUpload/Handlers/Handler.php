<?php

namespace App\Lib\BulkUpload\Handlers;

use App\Lib\BulkUpload\Record;

interface Handler
{
    /**
     * Process a batch of bulk-upload records, mutating each record's
     * status / details / changes fields in place.
     *
     * Records whose person is null are left untouched; the dispatcher
     * reports those as callsign-not-found.
     *
     * @param list<Record> $records
     */
    public function process(array $records, string $action, bool $commit, string $reason): void;
}

<?php

use App\Models\TimesheetMissingNote;
use App\Models\TimesheetNote;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('timesheet_note', function (Blueprint $table) {
            $table->id();
            $table->integer('timesheet_id')->nullable(false);
            $table->text('note')->nullable(false);
            $table->integer('create_person_id')->nullable(true);

            $table->enum('type', [TimesheetNote::TYPE_USER, TimesheetNote::TYPE_HQ_WORKER, TimesheetNote::TYPE_WRANGLER, TimesheetNote::TYPE_ADMIN])->nullable(false)->default(TimesheetNote::TYPE_USER);
            $table->datetime('created_at')->nullable(false);
        });

        Schema::create('timesheet_missing_note', function (Blueprint $table) {
            $table->id();
            $table->integer('timesheet_missing_id')->nullable(false);
            $table->enum('type', [TimesheetMissingNote::TYPE_USER, TimesheetMissingNote::TYPE_HQ_WORKER, TimesheetMissingNote::TYPE_WRANGLER, TimesheetMissingNote::TYPE_ADMIN])->nullable(false)->default(TimesheetNote::TYPE_USER);
            $table->text('note')->nullable(false);
            $table->integer('create_person_id')->nullable(true);
            $table->datetime('created_at')->nullable(false);
        });

        $this->importMissingNotes();
    }

    public function importMissingNotes(): void
    {
        function patch($str): string
        {
            $lines = explode("\n", $str);
            $header = array_shift($lines);
            if (!str_starts_with($header, 'From ') && !preg_match('/^\d+\/\d+\/\d+/', $header)) {
                return $str;
            }
            return trim(implode("\n", $lines));
        }

        DB::table('action_logs')
            ->select('action_logs.*', 'timesheet_missing.id as has_request')
            ->leftJoin('timesheet_missing', 'timesheet_missing.id', DB::raw('json_value(data, "$.id")'))
            ->whereIn('event', ['timesheet-missing-create', 'timesheet-missing-update'])
            ->whereRaw('json_extract(data, "$.notes") is not null or json_extract(data, "$.reviewer_notes") is not null')
            ->orderBy('id')
            ->chunk(2000, function ($rows) {
                foreach ($rows as $row) {
                    if (!$row->has_request) {
                        continue;
                    }

                    $data = json_decode($row->data);

                    if (isset($data->reviewer_notes)) {
                        $type = TimesheetNote::TYPE_WRANGLER;
                        $note = $row->event == 'timesheet-missing-create' ? $data->reviewer_notes : $data->reviewer_notes[1];
                    } else {
                        $note = ($row->event == 'timesheet-missing-create' ? $data->notes : $data->notes[1]);
                        $type = ($row->person_id == $row->target_person_id) ? TimesheetNote::TYPE_USER : TimesheetNote::TYPE_HQ_WORKER;
                    }

                    $note = patch($note);
                    DB::table('timesheet_missing_note')
                        ->insert([
                            'timesheet_missing_id' => $data->id,
                            'create_person_id' => $row->person_id,
                            'type' => $type,
                            'note' => $note,
                            'created_at' => $row->created_at,
                        ]);
                }
            });
    }

    public function importNotes()
    {
        DB::table('timesheet_log')
            ->select('timesheet_log.*', 'timesheet.id as has_timesheet')
            ->leftJoin('timesheet', 'timesheet.id', 'timesheet_log.timesheet_id')
            ->whereRaw('json_extract(data, "$.notes") is not null or json_extract(data, "$.reviewer_notes") is not null')
            ->orderBy('id')
            ->chunk(1000, function ($rows) {
                error_log("Chunk");
                foreach ($rows as $row) {
                    if (!$row->has_timesheet) {
                        continue;
                    }

                    $data = json_decode($row->data);

                    if (isset($data->reviewer_notes)) {
                        $type = TimesheetNote::TYPE_WRANGLER;
                        $note = $data->reviewer_notes;
                    } else {
                        $note = $data->notes;
                        $type = ($row->create_person_id == $row->person_id) ? TimesheetNote::TYPE_USER : TimesheetNote::TYPE_HQ_WORKER;
                    }

                    DB::table('timesheet_note')
                        ->insert([
                            'timesheet_id' => $row->timesheet_id,
                            'create_person_id' => $row->create_person_id,
                            'type' => $type,
                            'note' => $note,
                            'created_at' => $row->created_at,
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timesheet_note');
        Schema::dropIfExists('timesheet_missing_note');
    }
};

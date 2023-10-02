<?php

use App\Models\Position;
use App\Models\Timesheet;
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
        // Patch up the missing create date based on Alpha timesheet entries
        $rows = DB::table('person')->whereNull('create_date')->get();
        foreach ($rows as $row) {
            $entry = Timesheet::where('person_id', $row->id)->where('position_id', Position::ALPHA)
                ->orderBy('on_duty', 'desc')
                ->first();
            if ($entry) {
                DB::table('person')->where('id', $row->id)->update(['create_date' => $entry->on_duty->year . "-08-01"]);
                continue;
            }
            $entry = Timesheet::where('person_id', $row->id)->where('position_id', Position::DIRT)
                ->orderBy('on_duty', 'asc')
                ->first();
            if ($entry) {
                DB::table('person')->where('id', $row->id)->update(['create_date' => $entry->on_duty->year . "-08-01"]);
            }
        }
        Schema::table('person', function (Blueprint $table) {
            $table->renameColumn('create_date', 'created_at');
            $table->renameColumn('timestamp', 'updated_at');
            $table->renameColumn('long_sleeve_swag_ig', 'long_sleeve_swag_id');
        });

        Schema::table('bmid', function (Blueprint $table) {
            $table->renameColumn('create_datetime', 'created_at');
            $table->renameColumn('modified_datetime', 'updated_at');
        });

        Schema::table('access_document', function (Blueprint $table) {
            $table->renameColumn('create_date', 'created_at');
            $table->renameColumn('modified_date', 'updated_at');
        });

        Schema::table('access_document_changes', function (Blueprint $table) {
           $table->renameColumn('timestamp', 'created_at');
        });

        Schema::table('person_message', function (Blueprint $table) {
            $table->renameColumn('timestamp', 'created_at');
        });

        Schema::dropIfExists('access_document_delivery');
        Schema::dropIfExists('lambase_photo');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};

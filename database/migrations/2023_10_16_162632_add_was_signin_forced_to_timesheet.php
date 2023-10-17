<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('timesheet', function (Blueprint $table) {
            $table->boolean('was_signin_forced')->nullable(false)->default(false);
        });

        $ids = DB::table('timesheet_log')
            ->whereRaw('json_extract(timesheet_log.data, "$.forced") is not null')
            ->pluck('timesheet_id');

        DB::table('timesheet')->whereIntegerInRaw('id', $ids) ->update([ 'was_signin_forced' => true ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timesheet', function (Blueprint $table) {
            $table->dropColumn('was_signin_forced');
        });
    }
};

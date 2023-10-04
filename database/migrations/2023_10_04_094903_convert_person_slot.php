<?php

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

        $rows = DB::table('person_slot')
            ->select('person_slot.id', 'slot.begins')
            ->where('timestamp', '0000-00-00 00:00:00')
            ->join('slot', 'slot.id', 'person_slot.slot_id')
            ->get();

        foreach ($rows as $row) {
            DB::table('person_slot')->where('id', $row->id)
                ->update(['timestamp' => (string)$row->begins]);
        }

        Schema::table('person_slot', function (Blueprint $table) {
            $table->renameColumn('timestamp', 'created_at');
        });
        //
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('person_slot', function (Blueprint $table) {
            $table->renameColumn('created_at', 'timestamp');
        });
    }
};

<?php

use App\Models\HandleReservation;
use App\Models\Person;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('handle_reservation', function (Blueprint $table) {
            $table->string('normalized_handle')->default('')->nullable(false);
        });

        HandleReservation::chunk(200, function ($rows) {
            foreach ($rows as $row) {
                $row->normalized_handle = Person::normalizeCallsign($row->handle);
                $row->setAuditModel(false);
                $row->save();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('handle_reservation', function (Blueprint $table) {
            $table->dropColumn('normalized_handle');
        });
    }
};

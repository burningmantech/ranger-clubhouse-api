<?php

use App\Models\HandleReservation;
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
        Schema::table('handle_reservation', function (Blueprint $table) {
            $table->integer('twii_year')->nullable(true)->default(null);
            $table->dropColumn('start_date');
            $table->renameColumn('end_date', 'expires_on');
        });

        $enums = implode(',' , array_map(fn ($e) => '"'.$e.'"', [
            HandleReservation::TYPE_BRC_TERM,
            HandleReservation::TYPE_DECEASED_PERSON,
            HandleReservation::TYPE_DISMISSED_PERSON,
            HandleReservation::TYPE_OBSCENE,
            HandleReservation::TYPE_PHONETIC_ALPHABET,
            HandleReservation::TYPE_RADIO_JARGON,
            HandleReservation::TYPE_RANGER_TERM,
            HandleReservation::TYPE_SLUR,
            HandleReservation::TYPE_TWII_PERSON,
            HandleReservation::TYPE_UNCATEGORIZED,
        ]));
        DB::statement("ALTER TABLE handle_reservation CHANGE COLUMN reservation_type reservation_type ENUM($enums) NOT NULL DEFAULT '".HandleReservation::TYPE_UNCATEGORIZED."'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('handle_reservation', function (Blueprint $table) {
            $table->dropColumn('twii_year');
        });
    }
};

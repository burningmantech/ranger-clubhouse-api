<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CleanupLegacySchemaMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('person', function (Blueprint $table) {
            $table->dropColumn([
                'barcode',
                'birthdate',
                'em_first_name',
                'em_mi',
                'em_last_name',
                'em_handle',
                'em_home_phone',
                'em_alt_phone',
                'em_email',
                'em_camp_location',
                'lam_status',
                'alternate_callsign'
            ]);
        });

        $dropTables = [
            'early_arrival',
            'feedback',
            'person_xfield',
            'person_help',
            'sessions',
            'ticket',
            'training',
            'xfield',
            'xgroup',
            'xoption'
        ];

        foreach ($dropTables as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}

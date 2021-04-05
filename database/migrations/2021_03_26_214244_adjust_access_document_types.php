<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AdjustAccessDocumentTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE access_document CHANGE COLUMN type type enum('gift_ticket','reduced_price_ticket','staff_credential','vehicle_pass','work_access_pass','work_access_pass_so','all_eat_pass','wet_spot','wet_spot_pog','event_radio','event_eat_pass') NOT NULL");
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

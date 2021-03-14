<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdjustAccessDocuments extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('access_document', function ($table) {
            $table->integer('item_count')->default(0)->nullable(false);
        });
        DB::statement("ALTER TABLE access_document CHANGE COLUMN type type enum('gift_ticket','reduced_price_ticket','staff_credential','vehicle_pass','work_access_pass','work_access_pass_so', 'all_you_can_eat', 'wet_spot', 'wet_spot_pog', 'event_radio') not null");
        DB::statement("ALTER TABLE access_document_delivery CHANGE COLUMN method method enum('will_call', 'mail', 'none') not null default 'none'");

        DB::statement(
            "INSERT INTO access_document (person_id, type, status, item_count, source_year, expiry_date, create_date, comments)
                SELECT person_id, 'event_radio' as type, 
                       'used' as status, 
                        max_radios as item_count,
                       year as source_year, concat(year, '-09-15') as expiry_date,
                       concat(year, '-02-01 00:00:00') as create_date,
                       'imported from radio_eligible table' as comments
                FROM radio_eligible
                WHERE radio_eligible.year > 0
              ");
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePersonEventTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('person_event', function (Blueprint $table) {
            $table->bigInteger('person_id')->nullable(false);
            $table->integer('year')->nullable(false);
            $table->boolean('may_request_stickers')->nullable(false)->default(true);
            $table->boolean('org_vehicle_insurance')->nullable(false)->default(false);
            $table->boolean('signed_motorpool_agreement')->nullable(false)->default(false);
            $table->boolean('signed_personal_vehicle_agreement')->nullable(false)->default(false);
            $table->boolean('asset_authorized')->nullable(false)->default(false);
            $table->datetime('timesheet_confirmed_at')->nullable(true);
            $table->boolean('timesheet_confirmed')->nullable(false)->default(false);
            $table->boolean('sandman_affidavit')->nullable(false)->default(false);
            $table->unique([ 'year', 'person_id' ]);
        });

        Schema::table('person', function(Blueprint $table) {
           $table->dropColumn(['asset_authorized', 'vehicle_paperwork', 'vehicle_insurance_paperwork']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('person_event');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTypesToMotd extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('motd', function (Blueprint $table) {
            $table->boolean('for_rangers')->default(false);
            $table->boolean('for_pnvs')->default(false);
            $table->boolean('for_auditors')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('motd', function (Blueprint $table) {
            $table->dropColumn([ 'for_rangers', 'for_pnvs', 'for_auditors' ]);
        });
    }
}

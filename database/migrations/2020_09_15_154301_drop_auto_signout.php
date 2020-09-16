<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropAutoSignout extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('position', function (Blueprint $table) {
        $table->dropColumn('auto_signout');
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::table('position', function (Blueprint $table) {
        $table->boolean('auto_signout')->default(0)->comment('Can be auto-signed out');
      });
    }
}

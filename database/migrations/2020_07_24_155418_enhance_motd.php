<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EnhanceMotd extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('motd', function (Blueprint $table) {
           $table->dateTime('expires_at')->nullable(false);
           $table->string('subject')->nullable(false);
           $table->index(['expires_at']);
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
            $table->dropColumn([ 'expires_at', 'subject' ]);
        });
    }
}

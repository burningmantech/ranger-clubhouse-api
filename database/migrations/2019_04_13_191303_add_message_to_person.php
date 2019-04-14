<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMessageToPerson extends Migration
{
    /**
     * Add a note to the person record.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('person', function (Blueprint $table) {
            $table->text('message');
            $table->dateTime('message_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('person', function (Blueprint $table) {
            $table->dropColumn([ 'message', 'message_updated_at' ]);
        });
    }
}

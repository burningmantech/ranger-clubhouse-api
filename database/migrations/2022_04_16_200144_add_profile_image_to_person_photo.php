<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('person_photo', function (Blueprint $table) {
            $table->string('profile_filename')->nullable(true);
            $table->integer('profile_width')->nullable(false)->default(0);
            $table->integer('profile_height')->nullable(false)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('person_photo', function (Blueprint $table) {
            $table->dropColumn(['profile_filename', 'profile_width', 'profile_height']);
        });
    }
};

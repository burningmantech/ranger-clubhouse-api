<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class DropMentorColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('person', function ($table) {
            $table->dropColumn(['mentors_flag', 'mentors_flag_note', 'mentors_notes']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('person', function ($table) {
            $table->boolean('mentors_flag')->default(0);
            $table->string('mentors_flag_note', 256)->nullable()->default('');
            $table->text('mentors_notes', 65535)->nullable();
        });
    }
}

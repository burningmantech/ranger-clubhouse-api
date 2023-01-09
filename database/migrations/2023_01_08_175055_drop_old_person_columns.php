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
        Schema::table('person', function (Blueprint $table) {
            $table->dropColumn([
                'osha10',
                'osha30',
                'lms_course',
                'lms_course_expiry',
                'sandman_affidavit',
                'longsleeveshirt_size_style',
                'teeshirt_size_style',
            ]);
        });
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
};

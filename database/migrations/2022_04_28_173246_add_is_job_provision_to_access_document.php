<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('access_document', function (Blueprint $table) {
            $table->boolean('is_job_provision')->nullable(false)->default(false);
            $table->index([ 'type', 'status']);
            $table->index([ 'person_id', 'status' ]);
            $table->index([ 'person_id', 'source_year']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('access_document', function (Blueprint $table) {
            $table->dropColumn('is_job_provision');
            $table->dropIndex([ 'type', 'status']);
            $table->dropIndex([ 'person_id', 'status' ]);
            $table->dropIndex([ 'person_id', 'source_year']);
        });
    }
};

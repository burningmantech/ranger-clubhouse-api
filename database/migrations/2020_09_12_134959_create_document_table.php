<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDocumentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('document', function (Blueprint $table) {
            $table->id();
            $table->string('tag')->nullable(false);
            $table->string('description')->nullable(false);
            $table->longText('body')->nullable(false);
            $table->integer('person_create_id')->nullable(false);
            $table->integer('person_update_id')->nullable(false);
            $table->timestamps();
            $table->unique([ 'tag' ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('document');
    }
}

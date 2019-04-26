<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateHelpTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('help', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('slug', 128)->unique('slug');
            $table->string('title', 128);
            $table->text('body');
            $table->string('tags', 255)->default('')->nullable();
            $table->timestamps();
        });

        Schema::create('person_help', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('person_id')->unsigned()->index('person_help_idx_person_id');
            $table->bigInteger('help_id')->unsigned()->index('person_help_idx_help_id');
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('help');
        Schema::dropIfExists('person_help');
    }
}

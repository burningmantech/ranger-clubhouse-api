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
        Schema::create('person_pog', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->integer('person_id')->nullable(false);
            $table->integer('issued_by_id');
            $table->integer('timesheet_id')->nullable(true);
            $table->string('pog')->nullable(false);
            $table->string('status')->nullable(false);
            $table->text('notes');
            $table->index(['person_id', 'pog', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('person_pog');
    }
};

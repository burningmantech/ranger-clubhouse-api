<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehicle extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vehicle', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('person_id')->nullable(true);
            $table->enum('type', [ 'personal', 'fleet']);
            $table->integer('event_year')->nullable(false);
            $table->enum('status', [ 'pending', 'approved', 'rejected'])->default('pending')->nullable(false);
            $table->string('vehicle_year')->nullable(false)->default('');
            $table->string('vehicle_class')->nullable(false)->default('');
            $table->string('vehicle_make')->nullable(false)->default('');
            $table->string('vehicle_model')->nullable(false)->default('');
            $table->string('vehicle_color')->nullable(false)->default('');
            $table->string('vehicle_type')->nullable(false)->default('');
            $table->string('license_state')->nullable(false)->default('');
            $table->string('license_number')->nullable(false)->default('');
            $table->string('rental_number')->nullable(false)->default('');
            $table->enum('driving_sticker', [ 'none', 'prepost', 'staff', 'other' ])->nullable(false)->default('none');
            $table->string('sticker_number')->nullable(false)->default('');
            $table->enum('fuel_chit', [ 'event', 'single-use', 'none'])->nullable(false)->default('none');
            $table->enum('ranger_logo', [ 'permanent-new', 'permanent-existing', 'event', 'none'])->nullable(false)->default('none');
            $table->enum('amber_light', [ 'department', 'already-has', 'none' ])->nullable(false)->default('none');
            $table->text('team_assignment')->nullable(false);
            $table->text('notes')->nullable(false);
            $table->text('response')->nullable(false);
            $table->text('request_comment')->nullable(false);
            $table->timestamps();
            $table->index('event_year');
            $table->index('person_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vehicle');
    }
}

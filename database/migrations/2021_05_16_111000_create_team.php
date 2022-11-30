<?php

use App\Models\Team;
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
        Schema::create('team', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable(false);
            $table->string('type')->nullable(false)->default(Team::TYPE_TEAM);
            $table->boolean('active')->nullable(false)->default(true);
            $table->timestamps();
        });

        Schema::create('team_role', function (Blueprint $table) {
            $table->integer('team_id')->nullable(false);
            $table->integer('role_id')->nullable(false);
            $table->unique(['team_id', 'role_id']);
        });

        Schema::create('person_team', function (Blueprint $table) {
            $table->id();
            $table->integer('person_id')->nullable(false);
            $table->integer('team_id')->nullable(false);
            $table->boolean('is_manager')->nullable(false)->default(false);
            $table->unique(['person_id', 'team_id']);
            $table->timestamps();
        });

        Schema::create('person_team_log', function (Blueprint $table) {
            $table->id();
            $table->integer('team_id')->nullable(false);
            $table->integer('person_id')->nullable(false);
            $table->date('joined_on');
            $table->date('left_on')->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('team');
        Schema::dropIfExists('team_role');
        Schema::dropIfExists('person_team');
        Schema::dropIfExists('person_team_log');
    }
};

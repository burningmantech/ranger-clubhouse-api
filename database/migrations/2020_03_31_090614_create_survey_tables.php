<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use App\Models\Role;

class CreateSurveyTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('survey', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->enum('type', ['trainer', 'training', 'slot'])->default('trainer')->nullable(false);
            $table->integer('position_id')->nullable(true);
            $table->string('title');
            $table->text('prologue');
            $table->text('epilogue');
            $table->timestamps();
        });

        Schema::create('survey_group', function (Blueprint $table) {
            $table->id();
            $table->integer('survey_id');
            $table->integer('sort_index')->default(1);
            $table->string('title')->nullable(false);
            $table->text('description')->nullable(false);
            $table->boolean('is_trainer_group')->default(false);
            $table->index(['survey_id', 'sort_index']);
            $table->timestamps();
        });

        Schema::create('survey_question', function (Blueprint $table) {
            $table->id();
            $table->integer('survey_id');
            $table->integer('survey_group_id');
            $table->integer('sort_index')->default(1);
            $table->boolean('is_required')->nullable(false)->default(false);
            $table->text('options');
            $table->text('description');
            $table->enum('type', ['rating', 'options', 'text']);
            $table->index(['survey_id']);
            $table->index(['survey_group_id', 'sort_index']);
            $table->string('code')->nullable(true);
            $table->timestamps();
        });

        Schema::create('survey_answer', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('person_id');
            $table->string('callsign')->nullable(true);
            $table->integer('survey_id');
            $table->integer('survey_question_id');
            $table->integer('survey_group_id');
            $table->text('response');
            $table->bigInteger('slot_id')->nullable(true);
            $table->bigInteger('trainer_id')->nullable(true);
            $table->boolean('can_share_name')->nullable(false)->default(true);
            $table->index([ 'trainer_id']);
            $table->index([ 'slot_id' ]);
            $table->index(['person_id']);
            $table->index(['survey_id']);
            $table->index(['survey_question_id']);
            $table->index(['survey_group_id']);
            $table->timestamps();
        });

        DB::table('role')->insert([
            'id' => Role::SURVEY_MANAGEMENT,
            'title' => 'Survey Management'
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */

    public function down()
    {
        Schema::dropIfExists('survey');
        Schema::dropIfExists('survey_group');
        Schema::dropIfExists('survey_question');
        Schema::dropIfExists('survey_answer');
    }
}

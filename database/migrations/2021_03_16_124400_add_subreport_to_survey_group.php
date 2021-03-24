<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddSubreportToSurveyGroup extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('survey_group', function (Blueprint $table) {
            $table->string('type')->nullable(false)->default('normal');
            $table->string('report_title');
        });

        Schema::table('survey_question', function (Blueprint $table) {
            $table->boolean('summarize_rating')->nullable(false)->default(false);
        });

        DB::statement("UPDATE survey_group SET type='trainer' WHERE is_trainer_group is true AND id > 0");
        DB::statement("UPDATE survey_group
                    SET type='separate-slot', report_title='ART Feedback'
                    WHERE title like '%Advanced Ranger Training%' AND id > 0");
        DB::statement("UPDATE survey_group
                    SET type='separate-summary', report_title='Ranger Manual Feedback'
                    WHERE title like '%Ranger Manual%' AND id > 0");
        DB::statement("UPDATE survey_question set summarize_rating=true where code in ('venue_rating','training_rating', 'trainer_rating') AND id > 0");
        Schema::table('survey_question', function (Blueprint $table) {
           $table->dropColumn('code');
        });
     }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('survey_group', function (Blueprint $table) {
            //
        });
    }
}

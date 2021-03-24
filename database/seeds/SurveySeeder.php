<?php

use Illuminate\Database\Seeder;

use App\Models\Position;

use Illuminate\Support\Facades\DB;

class SurveySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
 /*       DB::table('survey')->delete();
        DB::table('survey_group')->delete();
        DB::table('survey_question')->delete();
*/
        for ($year = 2012; $year <= 2019; $year++) {
            $surveyId = DB::table('survey')->insertGetId([
                'year' => $year,
                'position_id' => Position::TRAINING,
                'type' => 'training',
                'title' => "$year Ranger Training Feedback",
                'prologue' => 'Thank you for taking a few minutes to give the Training Academy your thoughts on the
Ranger Training that you recently attended!',
                'epilogue' => '<b>Thank you SO MUCH for your feedback!</b>  It is incredibly important for making our Ranger trainings better and
we appreciate you taking the time to share your thoughts with us.',
            ]);

            $venueId = DB::table('survey_group')->insertGetId([
                'survey_id' => $surveyId,
                'sort_index' => 1,
                'title' => 'Please Tell Us About The Training Location',
                'description' => ''
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $venueId,
                'sort_index' => 1,
                'type' => 'rating',
                'options' => '',
                'description' => 'Overall, how would you rate this location for training?',
                'is_required' => true
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $venueId,
                'sort_index' => 2,
                'type' => 'text',
                'options' => '',
                'description' => 'Any comments on this location\'s suitability for training (e.g., parking, public transit, noise, neighbors, safety, etc.)?',
                'is_required' => false,
            ]);

            $trainingId = DB::table('survey_group')->insertGetId([
                'survey_id' => $surveyId,
                'sort_index' => 2,
                'title' => 'Please Tell Us About The Training Overall',
                'description' => ''
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $trainingId,
                'sort_index' => 1,
                'type' => 'rating',
                'options' => '',
                'description' => 'How would you rate this training in terms of overall effectiveness?',
                'is_required' => true,
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $trainingId,
                'sort_index' => 2,
                'type' => 'text',
                'options' => '',
                'description' => 'What parts of the training did you find MOST interesting, useful, or effective?',
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $trainingId,
                'sort_index' => 3,
                'type' => 'text',
                'options' => '',
                'description' => 'What parts of the training did you find LEAST interesting, useful, or effective?',
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $trainingId,
                'sort_index' => 3,
                'type' => 'text',
                'options' => '',
                'description' => 'Were there any parts of the training that you think could have been covered in less time?  If so, which?',
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $trainingId,
                'sort_index' => 4,
                'type' => 'text',
                'options' => '',
                'description' => 'Were there any parts of the training that you would have liked to spend more time on, or additional topics you would like to see covered in the future?  If so, which?',
            ]);

            if ($year <= 2017) {
                $trainerId = DB::table('survey_group')->insertGetId([
                    'survey_id' => $surveyId,
                    'sort_index' => 3,
                    'title' => 'Please Tell Us About the RUdE Training for Vets',
                    'description' => 'These next two questions are only for Veteran (2+ year) Rangers who took the RUdE (Refresh, Update, Enhance) portion of training in the afternoon. <b>Alphas and first-year Rangers, please do not answer these questions.</b>'
                ]);

                DB::table('survey_question')->insert([
                    'survey_id' => $surveyId,
                    'survey_group_id' => $trainerId,
                    'sort_index' => 1,
                    'options' => '',
                    'type' => 'rating',
                    'description' => 'How would you rate the RUdE training in terms of overall effectiveness?',
                ]);

                DB::table('survey_question')->insert([
                    'survey_id' => $surveyId,
                    'survey_group_id' => $trainerId,
                    'sort_index' => 2,
                    'options' => '',
                    'type' => 'text',
                    'description' => 'What did you like or dislike about the RUdE training?  What would you like to see covered next year?',
                ]);

            }

            $trainerId = DB::table('survey_group')->insertGetId([
                'survey_id' => $surveyId,
                'sort_index' => 4,
                'title' => 'Please Tell Us About Your Trainer',
                 'description' => ''
           ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $trainerId,
                'sort_index' => 1,
                'type' => 'rating',
                'options' => '',
                'description' => 'How did this Trainer do in terms of effectively covering the training material in an engaging manner?',
                'is_required' => true,
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $trainerId,
                'sort_index' => 2,
                'type' => 'text',
                'options' => '',
                'description' => 'What did this Trainer do really well?  How can they improve?  Any other comments?',
            ]);

            $artId = DB::table('survey_group')->insertGetId([
                'survey_id' => $surveyId,
                'sort_index' => 5,
                'title' => 'Please Tell Us About Advanced Ranger Trainings (ARTs)',
                'description' => ''
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $artId,
                'sort_index' => 1,
                'type' => 'text',
                'options' => '',
                'description' => 'Did you take an Advanced Ranger Training (ART) module in the morning?
If so, please tell us which one and what you thought of it.  (If not, just skip this question.)',
            ]);


            $mrId = DB::table('survey_group')->insertGetId([
                'survey_id' => $surveyId,
                'sort_index' => 6,
                'title' => 'Please Tell Us About The Ranger Manual',
                'description' => ''
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $mrId,
                'sort_index' => 1,
                'type' => 'rating',
                'options' => '',
                'description' => 'Overall, how would you rate the Ranger Manual in terms of readability and usefulness',
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $mrId,
                'sort_index' => 2,
                'type' => 'text',
                'options' => '',
                'description' => 'How did you use the Ranger Manual during the training?',
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $mrId,
                'sort_index' => 3,
                'type' => 'text',
                'options' => '',
                'description' => "Any comments on the Ranger Manual?  For example, anything you think it's missing?",
            ]);

            $partingId = DB::table('survey_group')->insertGetId([
                'survey_id' => $surveyId,
                'sort_index' => 7,
                'title' => 'Parting Thoughts',
                'description' => ''
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $partingId,
                'sort_index' => 1,
                'type' => 'text',
                'options' => '',
                'description' => 'If you could give us one suggestion or piece of advice on how to make Ranger trainings better, what would it be?',
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $partingId,
                'sort_index' => 2,
                'type' => 'text',
                'options' => '',
                'description' => 'Anything else you\'d like to tell us?',
            ]);
        }
    }
}

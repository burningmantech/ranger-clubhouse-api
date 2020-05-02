<?php

use Illuminate\Database\Seeder;

use App\Models\Position;
use Illuminate\Support\Facades\DB;


class TrainerSurveySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
/*        DB::table('survey')->delete();
        DB::table('survey_group')->delete();
        DB::table('survey_question')->delete();*/

        for ($year = 2012; $year <= 2019; $year++) {
            $surveyId = DB::table('survey')->insertGetId([
                'year' => $year,
                'position_id' => Position::TRAINING,
                'type' => 'trainer',
                'title' => "$year Ranger Trainer/Associate Trainer Feedback",
                'prologue' => 'Thank you for taking a few minutes to share your thoughts on your fellow Trainers and Associate Trainers.
Please fill out this form once for each Trainer or Associate Trainers you have feedback on.
<p>
<b>Your written comments below will be shared verbatim with the person you\'re giving feedback about.</b>
If there is something you are uncomfortable sharing directly with this person,
please use the confidential section at the very bottom of this survey;  it will be read only by Training Academy personnel
and will not shared directly with the person you\'re giving feedback about.
</p>
<p>
Remember that good feedback follows the SAFE-T model: it should be Specific, Actionable, Factual, Empathetic and Timely.
For instance, instead of saying that someone "sucks at staying on time" :-), please give a specific example of where they didn\'t
stay on time and offer suggestions for how they could do better in the future.
</p>
',
                'epilogue' => 'Thank you SO MUCH for your feedback!  It is incredibly important for making our Trainers and TiTs and Trainings better and
we appreciate you taking the time to share your thoughts with us.
If you have additional feedback, we want to hear it.
Please send us mail at <a href="mailto:ranger-trainingacademy-list@burningman.org">ranger-trainingacademy-list@burningman.com.</a>',
            ]);

            $groupId = DB::table('survey_group')->insertGetId([
                'survey_id' => $surveyId,
                'sort_index' => 1,
                'title' => 'Qualitative Feedback',
                'description' => ''
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $groupId,
                'sort_index' => 1,
                'type' => 'text',
                'options' => '',
                'description' => 'What did this person do that worked really well or impressed you, if anything?',
                'code' => 'good',
                'is_required' => false
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $groupId,
                'sort_index' => 2,
                'type' => 'text',
                'options' => '',
                'description' => 'What did this person do that didn\'t work so well or made you concerned, if anything?',
                'code' => 'bad',
                'is_required' => false
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $groupId,
                'sort_index' => 3,
                'type' => 'text',
                'options' => '',
                'description' => 'What specific suggestions would you offer this person for next time?',
                'code' => 'suggestions',
                'is_required' => false
            ]);

            $groupId = DB::table('survey_group')->insertGetId([
                'survey_id' => $surveyId,
                'sort_index' => 2,
                'title' => 'Quantitative Feedback',
                'description' => 'For the below questions, please use a rating scale of 1 = terrible, 4 = ok, 7 = fantastic.
If you give this person low marks anywhere, please be sure to discuss why in the comments
above so they can understand what they need to work on.'
            ]);


            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $groupId,
                'sort_index' => 1,
                'type' => 'rating',
                'options' => '',
                'description' => 'How would you rate this person in terms of their ability to effectively cover the training material in an engaging manner?',
                'code' => 'overall_rating',
                'is_required' => false
            ]);


            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $groupId,
                'sort_index' => 2,
                'type' => 'rating',
                'options' => '',
                'description' => 'How would you rate this person in terms of their ability to display a "not about me" attituide during training?
(This means that their anecdotes were not all about them,
that they truly let the group discuss during discussion activities,
that they didn\'t hog the stage,
that their stories contributed to the group as a whole,
and that they took input from their fellow Trainers and Associate Trainers.)',
                'code' => 'nam_rating',
                'is_required' => false
            ]);


            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $groupId,
                'sort_index' => 3,
                'type' => 'rating',
                'options' => '',
                'description' => 'How would you rate this person in terms of their ability to stay on time during the training?',
                'code' => 'ontime_rating',
                'is_required' => false
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $groupId,
                'sort_index' => 4,
                'type' => 'rating',
                'options' => '',
                'description' => 'How would you rate this person in terms of their ability to stick to the curriculum?',
                'code' => 'followcurriculum_rating',
                'is_required' => false
            ]);


            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $groupId,
                'sort_index' => 5,
                'type' => 'rating',
                'options' => '',
                'description' => 'How would you rate this person in terms of their preparation to
teach the training?
                (This means that they knew which sections they were supposed to deliver,
they knew the material in those sections, they attended any prep calls or
            meetings, they had their notes with them, their notes were organized, etc.)',
                'code' => 'preparedness_rating',
                'is_required' => false
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $groupId,
                'sort_index' => 6,
                'type' => 'options',
                'options' => "Yes\nNo\nMaybe\nna",
                'description' => 'If this person was an Associate Trainer, do you think they\'re ready to graduate to Trainer?',
                'code' => 'tit_graduate',
                'is_required' => false
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $groupId,
                'sort_index' => 7,
                'type' => 'text',
                'options' => '',
                'description' => 'If you don\'t think they\'re ready to
graduate to Trainer, what would you want to see them demonstrate or improve upon before you\'d think they were ready?',
                'code' => 'tit_demonstrate',
                'is_required' => false
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $groupId,
                'sort_index' => 8,
                'type' => 'options',
                'options' => "Yes\nNo\n",
                'description' => 'For all of your comments above, have you given this person this feedback directly, either in person, by phone, or email?',
                'code' => 'gavefb',
                'is_required' => false
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $groupId,
                'sort_index' => 9,
                'type' => 'text',
                'options' => '',
                'description' => 'If not, why not?',
                'code' => 'didntgivefb_why',
                'is_required' => false
            ]);

            $groupId = DB::table('survey_group')->insertGetId([
                'survey_id' => $surveyId,
                'sort_index' => 3,
                'title' => 'Confidential Parting Thoughts',
                'description' => 'Your answer to the following question is confidential and will be read only by Training Academy personnel.  It will not be shared directly with the person you\'re giving feedback on.',
            ]);

            DB::table('survey_question')->insert([
                'survey_id' => $surveyId,
                'survey_group_id' => $groupId,
                'sort_index' => 1,
                'type' => 'text',
                'options' => '',
                'description' => 'Anything else you\'d like to tell us about this person?',
                'code' => 'confidential_comments',
                'is_required' => false
            ]);
        }
    }
}

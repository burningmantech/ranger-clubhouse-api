<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Meal pass combination
    const string ALL_EAT_PASS = 'all_eat_pass';
    const string EVENT_EAT_PASS = 'event_eat_pass';
    const string PRE_EVENT_EAT_PASS = 'pre_event_eat_pass';
    const string POST_EVENT_EAT_PASS = 'post_event_eat_pass';
    const string PRE_EVENT_EVENT_EAT_PASS = 'pre_event_event_eat_pass';
    const string PRE_POST_EAT_PASS = 'pre_post_eat_pass';
    const string EVENT_POST_EAT_PASS = 'event_post_event_eat_pass';

    const array MEAL_MATRIX = [
        self::ALL_EAT_PASS => 'pre+event+post',
        self::EVENT_EAT_PASS => 'event',
        self::PRE_EVENT_EAT_PASS => 'pre',
        self::POST_EVENT_EAT_PASS => 'post',
        self::PRE_EVENT_EVENT_EAT_PASS => 'pre+event',
        self::EVENT_POST_EAT_PASS => 'event+post',
        self::PRE_POST_EAT_PASS => 'pre+post'
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('provision', function (Blueprint $table) {
            $table->boolean('pre_event_meals')->nullable(false)->default(false);
            $table->boolean('event_week_meals')->nullable(false)->default(false);
            $table->boolean('post_event_meals')->nullable(false)->default(false);
        });

        foreach (self::MEAL_MATRIX as $type => $periods) {
            $pre = false;
            $event = false;
            $post = false;

            foreach (explode('+', $periods) as $meal) {
                switch ($meal) {
                    case 'pre':
                        $pre = true;
                        break;
                    case 'post':
                        $post = true;
                        break;
                    case 'event':
                        $event = true;
                        break;
                    default:
                        error_log("*** UNKNOWN [$meal]");
                        continue 2;
                }
            }

            DB::table('provision')->where('type', $type)
                ->update([
                    'type' => 'meals',
                    'pre_event_meals' => $pre,
                    'event_week_meals' => $event,
                    'post_event_meals' => $post,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

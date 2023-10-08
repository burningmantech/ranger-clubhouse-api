<?php

use App\Models\OnlineCourse;
use App\Models\Position;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('online_course', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(false)->default('');
            $table->integer('year')->nullable(false);
            $table->integer('position_id')->nullable(false);
            $table->string('course_id')->nullable(false)->default('');
            $table->boolean('active')->nullable(false)->default(true);
            $table->enum('course_for', ['all', 'returning', 'new'])->default('all')->nullable(false);
            $table->timestamps();
            $table->unique(['position_id', 'year', 'course_for']);
        });

        Schema::rename('person_online_training', 'person_online_course');
        Schema::table('person_online_course', function (Blueprint $table) {
            $table->integer('position_id')->nullable(false)->default(0);
            $table->integer('online_course_id')->nullable(false)->default(0);
            $table->integer('year')->nullable(false)->default(0);
            $table->datetime('enrolled_at')->nullable(true);
            $table->datetime('completed_at')->nullable(true)->default(null)->change();
        });

        $full2022 = OnlineCourse::create([
            'position_id' => Position::TRAINING,
            'year' => 2022,
            'course_id' => '11',
            'course_for' => OnlineCourse::COURSE_FOR_NEW,
            'active' => true,
        ]);

        $vet2022 = OnlineCourse::create([
            'position_id' => Position::TRAINING,
            'year' => 2022,
            'course_id' => '17',
            'course_for' => OnlineCourse::COURSE_FOR_RETURNING,
            'active' => true,
        ]);


        $all2023 = OnlineCourse::create([
            'position_id' => Position::TRAINING,
            'year' => 2023,
            'course_id' => '21',
            'course_for' => OnlineCourse::COURSE_FOR_ALL,
            'active' => true,
        ]);

        DB::table('person_online_course')
            ->update([
                'position_id' => Position::TRAINING,
                'year' => DB::raw('year(completed_at)')
            ]);


        DB::table('person_online_course')
            ->join('person_event', function ($j) {
                $j->on('person_online_course.person_id', 'person_event.person_id');
                $j->whereColumn('person_online_course.year', 'person_event.year');
            })->whereNotNull('lms_enrolled_at')
            ->update([
                'person_online_course.enrolled_at' => DB::raw('person_event.lms_enrolled_at'),
            ]);

        Schema::table('person_online_course', function (Blueprint $table) {
            $table->unique(['person_id', 'position_id', 'year']);
        });

        foreach ([$full2022, $vet2022, $all2023] as $course) {
            DB::table('person_online_course')
                ->join('person_event', function ($j) {
                    $j->on('person_online_course.person_id', 'person_event.person_id');
                    $j->whereColumn('person_online_course.year', 'person_event.year');
                })->where('person_online_course.year', $course->year)
                ->where('person_event.lms_course_id', $course->course_id)
                ->update(['online_course_id' => $course->id]);
        }

        Schema::table('person_event', function (Blueprint $table) {
            $table->dropColumn('lms_course_id');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('online_course');
    }
};


<?php

use App\Models\Survey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('survey', function (Blueprint $table) {
            $table->enum('type', [
                Survey::TRAINER,
                Survey::TRAINING,
                Survey::ALPHA,
                Survey::MENTOR_FOR_MENTEES,
                Survey::MENTEES_FOR_MENTOR,
            ])->nullable(false)->change();
            $table->integer('mentoring_position_id')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('survey', function (Blueprint $table) {
            $table->enum('type', [
                Survey::TRAINER,
                Survey::TRAINING,
                Survey::ALPHA,
            ])->nullable(false)->change();
            $table->dropColumn('mentoring_position_id');
        });
    }
};

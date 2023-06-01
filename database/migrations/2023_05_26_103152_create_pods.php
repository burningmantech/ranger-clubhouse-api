<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pod', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->enum('type', ['alpha', 'mentor', 'mitten', 'shift']);
            $table->datetime('formed_at')->nullable(false);
            $table->datetime('disbanded_at')->nullable(true)->default(null);
            $table->integer('slot_id')->nullable(true);
            $table->integer('mentor_pod_id')->nullable(true);
            $table->integer('person_count')->nullable(false)->default(0);
            $table->integer('sort_index')->nullable(false)->default(0);

            $table->index('slot_id');
            $table->index(['slot_id', 'formed_at']);
            $table->index('formed_at');
        });

        Schema::create('person_pod', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->integer('pod_id')->nullable(false);
            $table->integer('person_id')->nullable(false);
            $table->boolean('is_lead')->nullable(false)->default(false);
            $table->datetime('joined_at')->nullable(false);
            $table->datetime('left_at')->nullable(true)->default(null);
            $table->datetime('removed_at')->nullable(true)->default(null);
            $table->integer('sort_index')->nullable(false)->default(0);
            $table->integer('timesheet_id')->nullable(true);
            $table->index(['pod_id', 'person_id']);
            $table->index('timesheet_id');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pod');
        Schema::dropIfExists('person_pod');
    }
};

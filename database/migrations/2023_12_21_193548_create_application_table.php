<?php

use App\Models\ProspectiveApplication;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prospective_application', function (Blueprint $table) {
            $table->id();
            $table->string('status')->nullable(false)->default(ProspectiveApplication::STATUS_PENDING);
            $table->text('events_attended')->nullable(false)->default('');
            $table->string('salesforce_name')->nullable(false)->default('');
            $table->string('salesforce_id')->nullable(false)->default('');
            $table->string('sfuid')->nullable(false)->default('');
            $table->string('first_name')->nullable(false)->default('');
            $table->string('last_name')->nullable(false)->default('');
            $table->string('street')->nullable(false)->default('');
            $table->string('city')->nullable(false)->default('');
            $table->string('state')->nullable(false)->default('');
            $table->string('country')->nullable(false)->default('');
            $table->string('postal_code')->nullable(false)->default('');
            $table->string('phone')->nullable(false)->default('');
            $table->integer('year')->nullable(false);
            $table->string('email')->nullable(false);
            $table->string('bpguid')->nullable(false);
            $table->integer('person_id')->nullable(true);
            $table->text('why_volunteer')->nullable(false)->default('');
            $table->string('why_volunteer_review')->nullable(false)->default(ProspectiveApplication::WHY_VOLUNTEER_REVIEW_UNREVIEWED);
            $table->integer('review_person_id')->nullable(true);
            $table->datetime('reviewed_at')->nullable(true);
            $table->string('known_rangers')->nullable(false)->default('');
            $table->string('known_applicants')->nullable(false)->default('');
            $table->boolean('is_over_18')->nullable(false)->default(false);
            $table->text('handles')->nullable(false)->default('');
            $table->string('approved_handle')->nullable(false)->default('');
            $table->text('rejected_handles')->nullable(true);
            $table->text('regional_experience')->nullable(false)->default('');
            $table->string('regional_callsign')->nullable(false)->default('');
            $table->string('experience')->nullable(false)->default(ProspectiveApplication::EXPERIENCE_NONE);
            $table->string('emergency_contact')->nullable(false)->default('');
            $table->integer('assigned_person_id')->nullable(true);
            $table->integer('updated_by_person_id')->nullable(true);
            $table->datetime('updated_by_person_at')->nullable(true);
            $table->timestamps();
            $table->index(['year', 'status']);
        });

        Schema::create('prospective_application_log', function (Blueprint $table) {
            $table->id();
            $table->integer('prospective_application_id')->nullable(false);
            $table->integer('person_id')->nullable(true);
            $table->text('data')->nullable(true);
            $table->string('action')->nullable(false);
            $table->datetime('created_at')->nullable(false);
            $table->index('prospective_application_id');
        });

        Schema::create('prospective_application_note', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable(false);
            $table->integer('prospective_application_id')->nullable(false);
            $table->integer('person_id')->nullable(true);
            $table->text('note')->nullable(false);
            $table->datetime('created_at')->nullable(false);
            $table->index('prospective_application_id');
        });

        Schema::table('mail_log', function ($table) {
           $table->integer('prospective_application_id')->nullable(true);
           $table->index('prospective_application_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospective_application');
        Schema::dropIfExists('prospective_application_log');
        Schema::dropIfExists('prospective_application_note');
        Schema::table('mail_log', function ($table) {
            $table->dropColumn('prospective_application_id');
        });
    }
};

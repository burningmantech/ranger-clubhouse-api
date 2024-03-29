<?php

use App\Models\PersonLanguage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('person_language', function ($table) {
            $table->string('proficiency')->default(PersonLanguage::PROFICIENCY_UNKNOWN)->nullable(false);
            $table->string('language_custom', 32)->nullable(true);
            $table->index(['person_id', 'language_name', 'language_custom']);
        });
        //
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

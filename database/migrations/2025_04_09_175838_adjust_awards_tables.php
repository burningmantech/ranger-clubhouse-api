<?php

use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('award', function (Blueprint $table) {
            $table->boolean('awards_grants_service_year')->default(false)->nullable(false);
        });

        Schema::table('person_award', function (Blueprint $table) {
            $table->boolean('awards_grants_service_year')->default(false)->nullable(false);
        });

        Schema::table('position', function (Blueprint $table) {
            $table->boolean('awards_auto_grant')->default(false)->nullable(false);
            $table->boolean('awards_grants_service_year')->default(false)->nullable(false);
            $table->index(['awards_auto_grant']);
        });

        Schema::table('team', function (Blueprint $table) {
            $table->boolean('awards_auto_grant')->default(false)->nullable(false);
            $table->boolean('awards_grants_service_year')->default(false)->nullable(false);
            $table->index(['awards_auto_grant']);
        });

        Schema::table('person', function (Blueprint $table) {
            $table->text('years_of_awards')->nullable(false)->default('[]');
            $table->text('years_of_signups')->nullable(false)->default('[]');
        });

        DB::table('team')
            ->whereIn('type', [ Team::TYPE_CADRE, Team::TYPE_DELEGATION])
            ->update(['awards_eligible' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

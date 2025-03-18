<?php

use App\Models\Person;
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
        Schema::table('person', function (Blueprint $table) {
            $table->enum('status', [
                Person::ACTIVE,
                Person::ALPHA,
                Person::AUDITOR,
                Person::BONKED,
                Person::ECHELON,
                Person::DECEASED,
                Person::DISMISSED,
                Person::INACTIVE,
                Person::INACTIVE_EXTENSION,
                Person::PAST_PROSPECTIVE,
                Person::PROSPECTIVE,
                Person::RESIGNED,
                Person::RETIRED,
                Person::SUSPENDED,
                Person::UBERBONKED,
                'non ranger'
            ])->change();
        });

        DB::table('person')->where('status', 'non ranger')->update(['status' => Person::ECHELON]);

        Schema::table('person', function (Blueprint $table) {
            $table->enum('status', [
                Person::ACTIVE,
                Person::ALPHA,
                Person::AUDITOR,
                Person::BONKED,
                Person::ECHELON,
                Person::DECEASED,
                Person::DISMISSED,
                Person::INACTIVE,
                Person::INACTIVE_EXTENSION,
                Person::PAST_PROSPECTIVE,
                Person::PROSPECTIVE,
                Person::RESIGNED,
                Person::RETIRED,
                Person::SUSPENDED,
                Person::UBERBONKED,
            ])->change();
        });

        Schema::table('timesheet', function (Blueprint $table) {
            $table->renameColumn('is_non_ranger', 'is_echelon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {


        Schema::table('person', function (Blueprint $table) {
            $table->enum('status', [
                Person::ACTIVE,
                Person::ALPHA,
                Person::AUDITOR,
                Person::BONKED,
                Person::ECHELON,
                Person::DECEASED,
                Person::DISMISSED,
                Person::INACTIVE,
                Person::INACTIVE_EXTENSION,
                Person::PAST_PROSPECTIVE,
                Person::PROSPECTIVE,
                Person::RESIGNED,
                Person::RETIRED,
                Person::SUSPENDED,
                Person::UBERBONKED,
                'non ranger'
            ])->change();
        });

        DB::table('person')->where('status', Person::ECHELON)->update(['status' => 'non ranger']);

        Schema::table('person', function (Blueprint $table) {
            $table->enum('status', [
                Person::ACTIVE,
                Person::ALPHA,
                Person::AUDITOR,
                Person::BONKED,
                Person::DECEASED,
                Person::DISMISSED,
                Person::INACTIVE,
                Person::INACTIVE_EXTENSION,
                Person::PAST_PROSPECTIVE,
                Person::PROSPECTIVE,
                Person::RESIGNED,
                Person::RETIRED,
                Person::SUSPENDED,
                Person::UBERBONKED,
                'non ranger'
            ])->change();
        });

        Schema::table('timesheet', function (Blueprint $table) {
            $table->renameColumn('is_echelon', 'is_non_ranger');
        });
    }
};

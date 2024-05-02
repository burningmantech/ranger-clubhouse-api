<?php

use App\Models\AccessDocument;
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
        $newEnums = [
            AccessDocument::GIFT,
            AccessDocument::LSD,
            AccessDocument::SPT, // fka Reduced-Price Ticket (RPT)
            AccessDocument::STAFF_CREDENTIAL,
            AccessDocument::VEHICLE_PASS_SP,
            AccessDocument::VEHICLE_PASS_GIFT,
            AccessDocument::VEHICLE_PASS_LSD,
            AccessDocument::WAP,
            AccessDocument::WAPSO
        ];

        Schema::table('access_document', function (Blueprint $table) use ($newEnums): void {
            $table->enum('type', [...$newEnums, 'vehicle_pass'])->change();
        });

        DB::table('access_document')->where('type', 'vehicle_pass')->update(['type' => AccessDocument::VEHICLE_PASS_GIFT]);

        Schema::table('access_document', function (Blueprint $table) use ($newEnums): void {
            $table->enum('type', $newEnums)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

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
        $updateTypes = [
            AccessDocument::GIFT,
            AccessDocument::LSD,
            AccessDocument::SPT,
            AccessDocument::STAFF_CREDENTIAL,
            AccessDocument::VEHICLE_PASS,
            AccessDocument::VEHICLE_PASS_GIFT,
            AccessDocument::VEHICLE_PASS_LSD,
            AccessDocument::WAP,
            AccessDocument::WAPSO,
        ];

        DB::statement("ALTER TABLE `access_document` CHANGE `type` `type` ENUM("
            .implode(',', array_map(fn ($t) =>  "'$t'", [...$updateTypes, 'reduced_price_ticket'])).
             ") NOT NULL DEFAULT 'special_price_ticket'");
            DB::table('access_document')->where('type', 'reduced_price_ticket')->update([ 'type' => AccessDocument::SPT ]);
        DB::statement("ALTER TABLE `access_document` CHANGE `type` `type` ENUM("
            .implode(',', array_map(fn ($t) =>  "'$t'", $updateTypes)).
            ") NOT NULL DEFAULT 'special_price_ticket'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

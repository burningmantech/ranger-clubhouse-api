<?php

use App\Models\AccessDocument;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */


    public function up(): void
    {
        $newEnums = [
            AccessDocument::GIFT,
            AccessDocument::LSD,
            AccessDocument::RPT,
            AccessDocument::STAFF_CREDENTIAL,
            AccessDocument::VEHICLE_PASS,
            AccessDocument::VEHICLE_PASS_GIFT,
            AccessDocument::VEHICLE_PASS_LSD,
            AccessDocument::WAP,
            AccessDocument::WAPSO,
        ];

        $this->adjustEnums($newEnums);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $oldEnums = [
            AccessDocument::GIFT,
            AccessDocument::LSD,
            AccessDocument::RPT,
            AccessDocument::STAFF_CREDENTIAL,
            AccessDocument::VEHICLE_PASS,
            AccessDocument::WAP,
            AccessDocument::WAPSO,
        ];

        $this->adjustEnums($oldEnums);
    }

    public function adjustEnums(array $enums): void
    {
        DB::statement("ALTER TABLE access_document MODIFY COLUMN type ENUM(" . implode(',', array_map(fn($e) => "'$e'", $enums)) . ")");
    }
};

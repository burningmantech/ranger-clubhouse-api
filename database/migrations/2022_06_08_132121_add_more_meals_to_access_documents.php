<?php

use App\Models\AccessDocument;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    const ORIGINAL_ENUMS = [
        AccessDocument::GIFT,
        AccessDocument::RPT,
        AccessDocument::STAFF_CREDENTIAL,
        AccessDocument::VEHICLE_PASS,
        AccessDocument::WAP,
        AccessDocument::WAPSO,
        AccessDocument::ALL_EAT_PASS,
        AccessDocument::WET_SPOT,
        AccessDocument::WET_SPOT_POG,
        AccessDocument::EVENT_RADIO,
        AccessDocument::EVENT_EAT_PASS
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $enums = array_merge(self::ORIGINAL_ENUMS, AccessDocument::MEAL_TYPES);
        self::alterEnums(array_unique($enums));
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        self::alterEnums(self::ORIGINAL_ENUMS);
    }

    private static function alterEnums($enums)
    {
        $enums = implode(',', array_map(fn($e) => "'$e'", $enums));
        DB::statement("ALTER TABLE access_document MODIFY COLUMN type enum($enums)");
    }
};

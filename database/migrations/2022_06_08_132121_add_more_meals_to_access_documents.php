<?php

use App\Models\AccessDocument;
use App\Models\Provision;
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
        Provision::ALL_EAT_PASS,
        Provision::WET_SPOT,
        Provision::EVENT_RADIO,
        Provision::EVENT_EAT_PASS
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $enums = array_merge(self::ORIGINAL_ENUMS, Provision::MEAL_TYPES);
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

<?php

use App\Models\AccessDocument;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $types = [
            AccessDocument::QUALIFIED,
            AccessDocument::CLAIMED,
            AccessDocument::BANKED,
            AccessDocument::USED,
            AccessDocument::CANCELLED,
            AccessDocument::EXPIRED,
            AccessDocument::SUBMITTED,
            AccessDocument::TURNED_DOWN,
        ];

        $enums = [];
        foreach ($types as $t) {
            $enums[] = "'$t'";
        }
        $enums = implode(',', $enums);
        DB::statement("ALTER TABLE `access_document` CHANGE `status` `status` ENUM($enums) NOT NULL DEFAULT 'qualified';");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
};

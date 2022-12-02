<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::dropIfExists('access_document_delivery');
        Schema::table('access_document', function (Blueprint $table) {
            $table->dropColumn([ 'item_count', 'is_job_provision', 'is_allocated']);
        });

        DB::statement("ALTER TABLE access_document MODIFY type enum('staff_credential', 'reduced_price_ticket', 'gift_ticket', 'lsd_ticket', 'vehicle_pass', 'work_access_pass', 'work_access_pass_so')");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        //
    }
};

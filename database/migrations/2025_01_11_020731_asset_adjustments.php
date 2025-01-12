<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('asset', function (Blueprint $table) {
            $table->string('order_number')->nullable(true);
            $table->string('entity_assignment')->nullable(true);
            $table->renameColumn('category', 'group_name');
            $table->index(['order_number', 'year']);
        });

        Schema::table('asset_person', function (Blueprint $table) {
            $table->boolean('check_out_forced')->nullable(false)->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset', function (Blueprint $table) {
            $table->renameColumn('group_name','group_name');
            $table->dropColumn(['order_number', 'entity_assignment']);
        });

        Schema::table('asset_person', function (Blueprint $table) {
            $table->dropColumn('check_out_forced');
        });
    }
};

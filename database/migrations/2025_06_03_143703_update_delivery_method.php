<?php

use App\Models\AccessDocument;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $methods = [
            AccessDocument::DELIVERY_NONE,
            AccessDocument::DELIVERY_POSTAL,
            AccessDocument::DELIVERY_EMAIL,
            AccessDocument::DELIVERY_WILL_CALL,
            AccessDocument::DELIVERY_PRIORITY,
        ];

        Schema::table('access_document', function (Blueprint $table) use ($methods) {
            $table->enum('delivery_method', $methods)->nullable(false)->change();
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

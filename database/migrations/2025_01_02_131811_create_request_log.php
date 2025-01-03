<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('request_log', function (Blueprint $table) {
            $table->id();
            $table->integer('person_id')->nullable(true);
            $table->string('url')->nullable(false);
            $table->string('ips')->nullable(false);
            $table->integer('status')->nullable(false);
            $table->string('method')->nullable(false);
            $table->integer('response_size')->nullable(false);
            $table->float('completion_time')->nullable(false);
            $table->timestamp('created_at')->nullable(false)->useCurrent();

            $table->index('person_id');
            $table->index('created_at');
            $table->index([ 'status', 'created_at' ]);
         });
    }

    /**
     * Reverse the migrations.
     */

    public function down(): void
    {
        Schema::dropIfExists('request_log');
    }
};

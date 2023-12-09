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
        Schema::create('oauth_code', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable(false);
            $table->string('scope');
            $table->integer('oauth_client_id')->nullable(false);
            $table->integer('person_id')->nullable(false);
            $table->datetime('created_at')->nullable(false);
            $table->unique([ 'oauth_client_id', 'code']);
        });

        Schema::create('oauth_client', function (Blueprint $table) {
            $table->id();
            $table->string('client_id')->nullable(false);
            $table->string('description')->nullable(false);
            $table->string('secret')->nullable(false);
            $table->timestamps();
            $table->unique('client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_code');
        Schema::dropIfExists('oauth_client');
    }
};

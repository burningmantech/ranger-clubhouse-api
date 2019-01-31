<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateContactLogTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('contact_log')) {
            return;
        }
        Schema::create(
            'contact_log', function (Blueprint $table) {
                $table->bigInteger('id', true);
                $table->bigInteger('sender_person_id');
                $table->bigInteger('recipient_person_id');
                $table->string('action');
                $table->timestamp('sent_at')->nullable()->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->string('recipient_address');
                $table->string('subject')->nullable();
                $table->text('message', 65535)->nullable();
                $table->index(['recipient_person_id','sent_at'], 'recipient_person_id');
                $table->index(['sender_person_id','sent_at'], 'sender_person_id');
            }
        );
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('contact_log');
    }

}

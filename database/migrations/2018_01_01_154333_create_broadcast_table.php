<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBroadcastTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('broadcast')) {
            return;
        }
        Schema::create(
            'broadcast', function (Blueprint $table) {
                $table->bigInteger('id', true);
                $table->bigInteger('sender_id')->index('broadcast_idx_sender_id');
                $table->bigInteger('alert_id');
                $table->string('sms_message', 1600)->nullable();
                $table->string('sender_address')->nullable();
                $table->text('email_message', 65535)->nullable();
                $table->string('subject')->nullable();
                $table->boolean('sent_sms')->default(0);
                $table->boolean('sent_email')->default(0);
                $table->boolean('sent_clubhouse')->default(0);
                $table->integer('recipient_count')->default(0);
                $table->integer('sms_failed')->default(0);
                $table->integer('email_failed')->default(0);
                $table->timestamp('created_at')->nullable()->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->dateTime('retry_at')->nullable();
                $table->bigInteger('retry_person_id')->nullable();
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
        Schema::drop('broadcast');
    }

}

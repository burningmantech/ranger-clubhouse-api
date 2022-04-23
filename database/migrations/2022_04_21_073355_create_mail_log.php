<?php

use App\Models\Broadcast;
use App\Models\ContactLog;
use App\Models\MailLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('mail_log', function (Blueprint $table) {
            $table->id();
            $table->integer('person_id')->nullable(false);
            $table->integer('sender_id')->nullable(true);
            $table->string('from_email')->nullable(false);
            $table->string('to_email')->nullable(false);
            $table->string('subject')->nullable(true);
            $table->string('message_id');
            $table->boolean('did_bounce')->nullable(false)->default(false);
            $table->boolean('was_sent')->nullable(false)->default(false);
            $table->integer('broadcast_id')->nullable(true);
            $table->timestamps();
            $table->index(['person_id', 'created_at']);
            $table->index(['sender_id', 'created_at']);
        });

        DB::statement("ALTER TABLE mail_log ADD body LONGBLOB");

        Broadcast::chunk(100, function ($broadcasts) {
            foreach ($broadcasts as $broadcast) {
                $broadcast->load('messages');
                if (!$broadcast->sent_email) {
                    continue;
                }
                foreach ($broadcast->messages as $message) {
                    if ($message->direction == 'outbound'
                        && $message->address_type == 'email'
                        && $message->status == Broadcast::STATUS_SENT) {
                        MailLog::create([
                            'sender_id' => $broadcast->sender_id,
                            'person_id' => $message->person_id,
                            'broadcast_id' => $broadcast->id,
                            'created_at' => $broadcast->created_at,
                            'from_email' => !empty($broadcast->sender_address) ? $broadcast->sender_address : 'do-not-reply@burningman.org',
                            'to_email' => $message->address,
                            'message_id' => 'broadcast-' . $broadcast->id . '@burningman.org',
                        ]);
                    }
                }
            }
        });


        ContactLog::chunk(100, function ($logs) {
            $logs->load('sender_person');
            foreach ($logs as $cl) {
                MailLog::create([
                    'sender_id' => $cl->sender_person_id,
                    'person_id' => $cl->recipient_person_id,
                    'created_at' => $cl->sent_at,
                    'from_email' => 'do-not-reply@burningman.org',
                    'to_email' => $cl->recipient_address,
                    'body' => $cl->message,
                    'subject' => $cl->subject,
                    'message_id' => 'contact-' . $cl->id . '@burningman.org',
                ]);
            }
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_log');
    }
};

<?php

use App\Models\AccessDocument;
use App\Models\Provision;
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
        Schema::create('provision', function (Blueprint $table) {
            $table->id();
            $table->integer('person_id')->nullable(false);
            $table->integer('source_year')->nullable(false);
            $table->string('type')->nullable(false);
            $table->string('status')->nullable(false)->default('available');
            $table->date('expires_on')->nullable(false);
            $table->longText('comments')->nullable(true);
            $table->boolean('is_allocated')->nullable(false)->default(false);
            $table->integer('item_count')->nullable(true);
            $table->timestamps();
        });

        DB::table('access_document')
            ->whereIn('type', Provision::ALL_TYPES)
            ->orderBy('id')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    $p = new Provision;
                    $p->person_id = $row->person_id;
                    $p->type = $row->type;
                    $p->source_year = $row->source_year;
                    $p->status = ($row->status === AccessDocument::QUALIFIED ? Provision::AVAILABLE : $row->status);
                    $p->expires_on = $row->expiry_date;
                    $p->comments = $row->comments;
                    $p->addComment('Converted from access document to provision', null);
                    $p->item_count = $row->item_count;
                    $p->is_allocated = $row->is_allocated;
                    $p->auditModel = false;
                    $p->saveOrThrow();
                }
            });
        DB::table('access_document')->whereIn('type', Provision::ALL_TYPES)->delete();
 }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('provision');
    }
};

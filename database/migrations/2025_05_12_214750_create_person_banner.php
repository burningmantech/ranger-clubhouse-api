<?php

use App\Models\PersonBanner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('person_banner', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('person_id')->nullable(false)->index();
            $table->boolean('is_permanent')->nullable(false)->default(false);
            $table->text('message')->nullable(false);
            $table->timestamps();
            $table->unsignedBigInteger('creator_person_id')->nullable(true);
            $table->unsignedBigInteger('updater_person_id')->nullable(true);
            $table->index(['person_id', 'is_permanent', 'created_at']);
        });

        $rows = DB::table('person')
            ->select('id', 'message', 'message_updated_at')
            ->whereNotNull('message')
            ->where('message', '!=', '')
            ->get();

        foreach ($rows as $row) {
            $banner = new PersonBanner(
                [
                    'person_id' => $row->id,
                    'message' => $row->message,
                    'is_permanent' => true,
                ]
            );
            $banner->created_at = $row->message_updated_at;
            $banner->save();
        }

        Schema::table('person', function (Blueprint $table) {
            $table->dropColumn('message');
            $table->dropColumn('message_updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_banner');
    }
};

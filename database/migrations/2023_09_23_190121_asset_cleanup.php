<?php

use App\Models\Asset;
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
        DB::table('asset')->where('description', Asset::TYPE_KEY)->where('barcode', 'like', 'SAND%')->update(['description' => Asset::TYPE_GEAR]);
        DB::table('asset')->where('description', Asset::TYPE_KEY)->where('barcode', 'like', 'SM%')->update(['description' => Asset::TYPE_GEAR]);
        DB::table('asset')->where('description', [Asset::TYPE_AMBER, Asset::TYPE_KEY, Asset::TYPE_VEHICLE])->delete();
        DB::table('asset')->where('create_date', '0000-00-00 00:00:00')->update(['create_date' => "2009-08-15 12:00:00"]);
        $this->adjustDescription([
            Asset::TYPE_AMBER,
            Asset::TYPE_GEAR,
            Asset::TYPE_KEY,
            Asset::TYPE_RADIO,
            Asset::TYPE_TEMP_ID,
            Asset::TYPE_VEHICLE,
        ]);

        Schema::table('asset', function (Blueprint $table) {
            $table->dropColumn(['subtype', 'model', 'color', 'style']);
        });

        Schema::table('asset', function (Blueprint $table) {
            $table->renameColumn('description', 'type');
            $table->renameColumn('create_date', 'created_at');
            $table->integer('year')->nullable(false)->default(0);
            $table->index([ 'type', 'year', 'barcode']);
            $table->index([ 'year', 'barcode']);
        });

        DB::table('asset')->update([ 'year' => DB::raw('YEAR(created_at)')]);
        Schema::table('asset', function (Blueprint $table) {
            $table->renameColumn('temp_id', 'description');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }

    public function adjustDescription(array $enums): void
    {
        DB::statement("ALTER TABLE asset MODIFY COLUMN description ENUM (" . implode(',', array_map(fn($e) => "'$e'", $enums)) . ")");
    }
};

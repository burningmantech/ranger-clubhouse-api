<?php

use App\Models\Person;
use App\Models\PersonFka;
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
        Schema::create('person_fka', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('person_id')->nullable(false);
            $table->datetime('created_at')->nullable(true);
            $table->string('fka')->nullable(false);
            $table->string('fka_normalized')->nullable(false);
            $table->string('fka_soundex')->nullable(false);
            $table->boolean('is_irrelevant')->nullable(false)->default(false);
            $table->index(['person_id', 'fka']);
            $table->index(['person_id', 'fka_normalized']);
            $table->index(['person_id', 'fka_soundex']);
        });

        Person::select('id', 'formerly_known_as')
            ->whereNotNull('formerly_known_as')
            ->where('formerly_known_as', '!=', '')
            ->chunk(500, function ($rows) {
                $data = [];
                foreach ($rows as $row) {
                    $fkas = Person::splitCommas($row->formerly_known_as);
                    if (empty($fkas)) {
                        continue;
                    }
                    foreach ($fkas as $fka) {
                        $data[] = [
                            'person_id' => $row->id,
                            'fka' => $fka,
                            'fka_normalized' => Person::normalizeCallsign($fka),
                            'fka_soundex' => metaphone(Person::spellOutNumbers($fka)),
                            'is_irrelevant' => (bool)preg_match(PersonFka::IRRELEVANT_REGEXP, $fka),
                        ];
                    }
                }
                if (!empty($data)) {
                    DB::table('person_fka')->insert($data);
                }
            });

        Schema::table('person', function (Blueprint $table) {
            $table->dropColumn('formerly_known_as');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_fka');
    }
};

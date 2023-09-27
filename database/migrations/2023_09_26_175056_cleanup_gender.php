<?php

use App\Lib\SummarizeGender;
use App\Models\Person;
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
        Schema::table('person', function (Blueprint $table) {
            $table->renameColumn('gender', 'gender_custom');
            $table->enum('gender_identity', [
                Person::GENDER_CIS_FEMALE,
                Person::GENDER_CIS_MALE,
                Person::GENDER_CUSTOM,
                Person::GENDER_FEMALE,
                Person::GENDER_FLUID,
                Person::GENDER_MALE,
                Person::GENDER_NONE,
                Person::GENDER_NON_BINARY,
                Person::GENDER_QUEER,
                Person::GENDER_TRANS_FEMALE,
                Person::GENDER_TRANS_MALE,
                Person::GENDER_TWO_SPIRIT,
            ])->nullable(false)->default(Person::GENDER_NONE);
            //
        });

        DB::table('person')->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $row) {
                $id = self::normalizeGender($row->gender_custom ?? '');
                if ($id == Person::GENDER_CUSTOM) {
                    $check = trim($row->gender ?? '');
                    if (stripos($check, $row->last_name) !== false || stripos($check, $row->first_name)) {
                        DB::table('person')->where('id', $row->id)->update([ 'gender_identity' => Person::GENDER_NONE, 'gender_custom' => '']);
                    } else {
                        DB::table('person')->where('id', $row->id)->update(['gender_identity' => Person::GENDER_CUSTOM]);
                    }
                } else {
                    DB::table('person')->where('id', $row->id)->update([ 'gender_identity' => $id, 'gender_custom' => '']);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('person', function (Blueprint $table) {
            //
        });
    }

    public static function normalizeGender(?string $gender) : string
    {
        $check = Person::normalizeCallsign($gender);

        if (empty($check)) {
            return Person::GENDER_NONE;
        }

        if ($check == 'cisfemale' || $check == 'femalecis' || $check == 'cisgenderwoman') {
            return Person::GENDER_CIS_FEMALE;
        }

        if ($check == 'cismale' || $check == 'malecis') {
            return Person::GENDER_CIS_MALE;
        }


// Female gender
        if (preg_match('/^(female|girl|femme|lady|she|her|woman|famale|femal|fem|f|femmefatale)$/', $check)) {
            return Person::GENDER_FEMALE;
        }

// Male gender
        if (preg_match('/^(m|male|dude|fella|man|boy|mr|guy|imaguy)$/', $check)) {
            return Person::GENDER_MALE;
        }

// Non-Binary
        if ($check == 'nonbinary'
            || $check == 'nb'
            || $check == 'enby'
            || $check == 'nonbianary'
            || $check == 'nonbinarytheythem'
            || $check == 'nobinarytheythemtheirs') {
            return Person::GENDER_NON_BINARY;
        }

// Queer (no gender stated)
        if ($check == 'queer' || $check == 'genderqueer') {
            return Person::GENDER_QUEER;
        }

// Gender Fluid
        if ($check == 'fluid' || $check == 'genderfluid') {
            return Person::GENDER_FLUID;
        }

// Gender "yes"? what does that even mean?
        if ($check == 'yes') {
            return Person::GENDER_NONE;
        }

        if ($check == 'transgenderfemale') {
            return Person::GENDER_TRANS_FEMALE;
        }

        return Person::GENDER_CUSTOM;
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;

class ClubhousePhoto extends Migration
{
    /**
     * Adjust person_photo to bring photo management into the Clubhouse
     *
     * @return void
     */
    public function up()
    {
        /*
         * Swap the person_table over from representing a Lambase photo
         * to its own thing with (machine learning) analysis and rejection info.
         */

        Schema::rename('person_photo', 'lambase_photo');

        Schema::create('person_photo', function (Blueprint $table) {
            $table->bigInteger('id', true);

            // Photo for person
            $table->bigInteger('person_id');
            $table->index([ 'person_id' ]);

            $table->enum('status', [ 'approved', 'rejected', 'submitted' ])->default('submitted');

            $table->string('image_filename');
            $table->integer('width')->default(0);
            $table->integer('height')->default(0);

            $table->string('orig_filename');
            $table->integer('orig_width')->default(0);
            $table->integer('orig_height')->default(0);

            $table->text('reject_reasons')->nullable();
            $table->longText('reject_message')->nullable();

            $table->datetime('reviewed_at')->nullable();
            $table->bigInteger('review_person_id')->nullable();

            $table->datetime('uploaded_at')->nullable();
            $table->bigInteger('upload_person_id')->nullable();

            $table->datetime('edited_at')->nullable();
            $table->bigInteger('edit_person_id')->nullable();

            $table->longText('analysis_info')->nullable();
            $table->enum('analysis_status', [ 'success', 'failed', 'none' ])->default('none');

            $table->timestamps();
        });

        /*
         * Since person_photo is no longer a 1-to-1 mapping between a photo and person,
         * associate the person record with a photo.
         */

         Schema::table('person', function (Blueprint $table) {
             $table->bigInteger('person_photo_id')->nullable();
         });

        // Kill the Lambase (and related) settings.
        DB::table('setting')->whereIn('name', [
            'LambaseImageUrl', 'LambaseJumpinUrl', 'LambasePrintStatusUpdateUrl',
            'LambaseReportUrl', 'LambaseStatusUrl',
            'PhotoStoreLocally', 'PhotoSource'
        ])->delete();

        $setting = Setting::where('name', 'PhotoStorage')->first();
        if (!$setting) {
            $setting = new Setting([ 'name' => 'PhotoStorage']);
        }
        $setting->value = 'photos-s3';
        $setting->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePersonTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('person')) {
            return;
        }
        Schema::create(
            'person', function (Blueprint $table) {
                $table->bigInteger('id', true);
                $table->string('first_name', 25);
                $table->string('mi', 10)->default('');
                $table->string('last_name', 25);
                $table->string('callsign', 64)->unique('callsign');
                $table->string('barcode', 25)->nullable()->unique('barcode');
                $table->string('street1', 50)->default('');
                $table->string('street2', 50)->default('');
                $table->string('apt', 10)->default('');
                $table->string('city', 50)->default('');
                $table->string('state', 2)->default('');
                $table->string('zip', 10)->default('');
                $table->string('country', 25)->default('');
                $table->date('birthdate')->nullable();
                $table->string('home_phone', 25)->default('');
                $table->string('alt_phone', 25)->default('');
                $table->string('email', 50)->nullable()->unique('email');
                $table->string('camp_location', 200)->default('');
                $table->string('em_first_name', 25)->default('')->comment('emergency contact');
                $table->string('em_mi', 10)->default('')->comment('emergency contact');
                $table->string('em_last_name', 25)->default('')->comment('emergency contact');
                $table->string('em_handle', 25)->default('')->comment('emergency contact');
                $table->string('em_home_phone', 25)->default('')->comment('emergency contact');
                $table->string('em_alt_phone', 25)->default('')->comment('emergency contact');
                $table->string('em_email', 50)->default('')->comment('emergency contact');
                $table->string('em_camp_location', 200)->default('');
                $table->boolean('on_site')->default(0);
                $table->boolean('asset_authorized')->default(0);
                $table->boolean('vehicle_blacklisted')->default(0);
                $table->boolean('vehicle_paperwork')->default(0);
                $table->boolean('vehicle_insurance_paperwork')->default(0);
                $table->date('date_verified')->nullable();
                $table->boolean('user_authorized')->default(1);
                $table->string('password', 64)->nullable();
                $table->dateTime('create_date');
                $table->boolean('has_note_on_file')->default(0);
                $table->boolean('callsign_approved')->default(0);
                $table->string('shirt_size', 10)->nullable();
                $table->enum('status', array('prospective','prospective waitlist','past prospective','alpha','bonked','active','inactive','inactive extension','retired','uberbonked','dismissed','resigned','deceased','auditor','non ranger', 'suspended'))->default('prospective');
                $table->date('status_date')->nullable();
                $table->string('tpassword', 64)->nullable();
                $table->integer('tpassword_expire')->nullable();
                $table->timestamp('timestamp')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('Most recent modification time.');
                $table->enum('lam_status', array('Requested','Printed','Pending','Other'))->default('Other');
                $table->string('shirt_style', 32)->nullable();
                $table->string('gender', 32)->nullable();
                $table->string('alternate_callsign', 32)->nullable();
                $table->string('bpguid', 64)->nullable();
                $table->string('sfuid', 64)->nullable();
                $table->text('emergency_contact', 65535)->nullable();
                $table->enum('longsleeveshirt_size_style', array('Unknown','Mens Regular S','Mens Regular M','Mens Regular L','Mens Regular XL','Mens Regular 2XL','Mens Regular 3XL','Mens Regular 4XL','Mens Tall M','Mens Tall L','Mens Tall XL','Mens Tall 2XL','Mens Tall 3XL','Mens Tall 4XL','Womens XS','Womens S','Womens M','Womens L','Womens XL','Womens 2XL','Womens 3XL'))->nullable();
                $table->enum('teeshirt_size_style', array('Unknown','Mens Crew S','Mens Crew M','Mens Crew L','Mens Crew XL','Mens Crew 2XL','Mens Crew 3XL','Mens Crew 4XL','Mens Crew 5XL','Womens V-Neck XS','Womens V-Neck S','Womens V-Neck M','Womens V-Neck L','Womens V-Neck XL','Womens V-Neck 2XL'))->nullable();
                $table->boolean('mentors_flag')->default(0);
                $table->string('mentors_flag_note', 256)->nullable()->default('');
                $table->string('formerly_known_as', 200)->nullable();
                $table->boolean('active_next_event')->default(0);
                $table->text('mentors_notes', 65535)->nullable();
                $table->boolean('vintage')->nullable()->default(0);
                $table->string('sms_off_playa')->nullable();
                $table->string('sms_on_playa')->nullable();
                $table->boolean('sms_off_playa_stopped')->default(0);
                $table->boolean('sms_on_playa_stopped')->default(0);
                $table->boolean('sms_off_playa_verified')->default(0);
                $table->boolean('sms_on_playa_verified')->default(0);
                $table->string('sms_off_playa_code', 16)->nullable();
                $table->string('sms_on_playa_code', 16)->nullable();
                $table->boolean('timesheet_confirmed')->default(0);
                $table->dateTime('timesheet_confirmed_at')->nullable();
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
        Schema::drop('person');
    }

}

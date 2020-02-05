<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ActionLog;
use App\Models\Clubhouse1Log;
use App\Models\PersonStatus;
use App\Models\Person;

class CreatePersonStatusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('person_status', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('person_id');
            $table->bigInteger('person_source_id')->nullable();
            $table->string('new_status');
            $table->string('old_status');
            $table->string('reason')->nullable();
            $table->datetime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->index([ 'person_id']);
            $table->index([ 'person_source_id']);
        });

        // Hunt down the old CH1 status change records.
        $rows = Clubhouse1Log::where('event', 'like', '=person.status%')->get();
        foreach ($rows as $row) {
            if (preg_match('/^=person\.status=(.*?)(,statusdate=NOW\(\))?@id=(\d+)\&status=(.*)$/', $row->event, $matches)) {
                $personId = $matches[3];
                if (!Person::where('id', $personId)->exists()) {
                    continue;
                }

                $oldStatus = $this->convertStatus($matches[4]);
                $newStatus = $this->convertStatus($matches[1]);
                PersonStatus::create([
                    'person_id' => (int)$personId,
                    'person_source_id' => $row->user_person_id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'reason' => $row->reason,
                    'created_at' => $row->occurred,
                ]);
            }
        }
        $rows = ActionLog::where('event', 'person-status-change')->get();
        foreach ($rows as $row) {
            $data = json_decode($row->data);
            $status = $data->status;
            PersonStatus::create([
                'person_id' => $row->target_person_id,
                'person_source_id' => $row->person_id,
                'old_status' => $status[0],
                'new_status' => $status[1],
                'reason' => $row->reason,
                'created_at' => $row->created_at
            ]);
        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('person_status');
    }

    private function convertStatus($status)
    {
        switch ($status) {
            case 'pastprospective':
                return 'past prospective';
            case 'prospectivewaitlist':
                return 'prospective waitlist';
            case 'nonranger':
                return 'non ranger';
            case 'inactiveextension':
                return 'inactive extension';
            default:
                return $status;
        }
    }

}

<?php
namespace App\Models;

use App\Models\ApiModel;

use App\Models\Person;
use App\Models\Motd;

use Illuminate\Support\Facades\DB;

class PersonMotd extends ApiModel
{
    protected $table = 'person_motd';
    protected $guarded = [];    // table is not directly accessible, allow anything

    public function person() {
        return $this->belongsTo(Person::class);
    }

    public function motd() {
        return $this->belongsTo(Motd::class);
    }

    public static function markAsRead($personId, $motdId)
    {
        self::updateOrCreate([ 'person_id' => $personId, 'motd_id' => $motdId],  [ 'read_at' => now() ]);
    }
}

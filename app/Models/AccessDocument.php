<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ApiModel;

class AccessDocument extends ApiModel
{
    protected $table = 'access_document';

    const INVALID_STATUSES = [
        'used',
        'cancelled',
        'expired'
    ];

    protected $fillable = [
        'person_id',
        'type',
        'status',
        'source_year',
        'access_date',
        'access_any_time',
        'name',
        'comments',
        'expiry_date',
        'create_date',
        'modified_date',
    ];

    protected $casts = [
        'access_date' => 'datetime',
        'expiry_date' => 'date',
        'create_date' => 'datetime',
        'modified_date' => 'timestamp',
    ];

    public static function findForQuery($query) {
        $sql = self::orderBy('source_year', 'type');

        if (empty($query['all'])) {
            $sql = $sql->whereNotIn('status', self::INVALID_STATUSES);
        }

        if (isset($query['person_id'])) {
            $sql = $sql->where('person_id', $query['person_id']);
        }

        if (isset($query['year'])) {
            $sql = $sql->where('source_year', $query['year']);
        }

        return $sql->orderBy('type', 'asc')->get();
    }

    public static function findSOWAPsForPerson($personId, $year) {
        return self::where('type', 'work_access_pass_so')
                        ->where('person_id', $personId)
                        ->whereNotIn('status', self::INVALID_STATUSES)
                        ->get();
    }

    public static function SOWAPCount($personId, $year) {
        return self::where('person_id', $personId)
                ->where('type', 'work_access_pass_so')
                ->whereNotIn('status', self::INVALID_STATUSES)
                ->count();
    }

    public static function findForPerson($personId, $id) {
        return self::where('person_id', $personId)
                    ->where('id', $id)
                    ->firstOrFail();
    }

    public static function createSOWAP($personId, $year, $name) {
        $wap = new AccessDocument;
        $wap->person_id = $personId;
        $wap->name = $name;
        $wap->type = 'work_access_pass_so';
        $wap->status = 'claimed';
        $wap->access_date = config('clubhouse.TAS_DefaultSOWAPDate');
        $wap->source_year = $year;
        $wap->expiry_date = $year;
        $wap->create_date = $wap->freshTimestamp();
        $wap->save();

        return $wap;
    }

    public function setExpiryDateAttribute($date) {
        if (gettype($date) == 'string' && strlen($date) == 4) {
            $date .= "-09-15 00:00:00";
        }

        $this->attributes['expiry_date'] = $date;
    }
}

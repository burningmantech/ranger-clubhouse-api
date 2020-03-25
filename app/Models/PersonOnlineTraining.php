<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Models\Person;

class PersonOnlineTraining extends ApiModel
{
    protected $table = 'person_online_training';

    protected $casts = [
        'completed_at' => 'datetime',
        'expires_at' => 'datetime'
    ];

    // Table is not directly accessible
    protected $guarded = [];

    const DOCEBO = 'docebo';
    const MANUAL_REVIEW = 'manual-review'; // prior to 2020

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public static function findForQuery($query)
    {
        $sql = self::select('person_online_training.*')
            ->with([ 'person:id,callsign,status' ])
            ->join('person', 'person.id', 'person_online_training.person_id');

        if (isset($query['year'])) {
            $sql->whereYear('completed_at', $query['year']);
        }

        if (isset($query['person_id'])) {
            $sql->where('person_id', $query['person_id']);
        }

        return $sql->get()->sortBy('person.callsign', SORT_NATURAL|SORT_FLAG_CASE)->values();
    }

    public static function findForPersonYear($personId, $year)
    {
        return self::whereYear('completed_at', $year)->where('person_id', $personId)->orderBy('completed_at', 'desc')->first();
    }

     public static function didCompleteForYear($personId, $year)
    {
        return self::where('person_id', $personId)->whereYear('completed_at', $year)->exists();
    }
 }

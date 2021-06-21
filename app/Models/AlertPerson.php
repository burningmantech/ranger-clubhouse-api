<?php

namespace App\Models;

use App\Models\ApiModel;
use Illuminate\Support\Facades\DB;

use App\Models\Alert;

class AlertPerson extends ApiModel
{
    protected $table = 'alert_person';

    protected $fillable = [
        'person_id',
        'alert_id',
        'use_email',
        'use_sms',
    ];

    /*
     * Find all alerts with opt defaults for person
     *
     * @param integer $personId user to look up
     */

    public static function findAllForPerson($personId)
    {
        return Alert::select(
            'alert.id',
            'alert.title',
            'alert.description',
            'alert.on_playa',
            DB::raw('IFNULL(use_sms, 1) as use_sms'),
            DB::raw('IFNULL(use_email, 1) as use_email')
        )->leftJoin('alert_person', function ($join) use ($personId) {
            $join->where('alert_person.person_id','=',$personId);
            $join->whereRaw('alert_person.alert_id=alert.id');
        })->orderBy('alert.on_playa', 'desc')->orderBy('alert.title')->get();
    }

    /*
     * Find or create an alert for a person
     *
     * @param integer $personId user to find alert for
     * @param integer $alertId id to lookup
     */

    public static function findOrCreateForPerson($personId, $alertId)
    {
        $query = [
            'person_id' => $personId,
            'alert_id'  => $alertId,
        ];
        $alert = self::where($query)->first();

        if ($alert) {
            return $alert;
        }

        return new AlertPerson($query);
    }

    /*
     * Find an alert for a person
     *
     * @param integer $alertId alert to find
     * @param interger $personId user to find
     */

    public static function findAlertForPerson($alertId, $personId) {
        return self::where('alert_id', $alertId)->where('person_id', $personId)->first();
    }

    /*
     * Figure out if the person is okay with sending email for a given alert.
     *
     * @param integer $personId person to check
     * @param integer $alertId alert to verify
     * @param boolean true if person allows emails
     */

    public static function allowEmailForAlert($personId, $alertId)
    {
        $pref = self::where('person_id', $personId)->where('alert_id', $alertId)->first();

        return ($pref == null || $pref['use_email'] == 1);
    }
}

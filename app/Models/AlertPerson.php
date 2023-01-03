<?php

namespace App\Models;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AlertPerson extends ApiModel
{
    protected $table = 'alert_person';

    protected $fillable = [
        'person_id',
        'alert_id',
        'use_email',
        'use_sms',
    ];

    /**
     * Find all alerts with opt defaults for person
     *
     * @param integer $personId user to look up
     * @return Collection
     */

    public static function findAllForPerson(int $personId): Collection
    {
        return Alert::select(
            'alert.id',
            'alert.title',
            'alert.description',
            'alert.on_playa',
            DB::raw('IFNULL(use_sms, 1) as use_sms'),
            DB::raw('IFNULL(use_email, 1) as use_email')
        )->leftJoin('alert_person', function ($join) use ($personId) {
            $join->where('alert_person.person_id', '=', $personId);
            $join->whereRaw('alert_person.alert_id=alert.id');
        })->orderBy('alert.on_playa', 'desc')->orderBy('alert.title')->get();
    }

    /**
     * Find or create an alert for a person
     *
     * @param int $personId user to find alert for
     * @param int $alertId id to lookup
     */

    public static function findOrCreateForPerson(int $personId, int $alertId): AlertPerson
    {
        $query = [
            'person_id' => $personId,
            'alert_id' => $alertId,
        ];
        $alert = self::where($query)->first();

        if ($alert) {
            return $alert;
        }

        return new AlertPerson($query);
    }

    /**
     * Find an alert for a person
     *
     * @param int $alertId alert to find
     * @param int $personId user to find
     * @return AlertPerson|null
     */

    public static function findAlertForPerson(int $alertId, int $personId): ?AlertPerson
    {
        return self::where('alert_id', $alertId)->where('person_id', $personId)->first();
    }

    /**
     * Figure out if the person is okay with sending email for a given alert.
     *
     * @param int $personId person to check
     * @param int $alertId alert to verify
     * @return bool true if person allows emails
     */

    public static function allowEmailForAlert(int $personId, int $alertId): bool
    {
        $pref = self::where('person_id', $personId)->where('alert_id', $alertId)->first();

        return ($pref == null || $pref['use_email'] == 1);
    }
}

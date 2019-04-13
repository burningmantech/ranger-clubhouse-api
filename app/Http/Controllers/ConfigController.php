<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ConfigController extends Controller
{
    /*
     * WARNING: Do not include a configuration variable that contains
     * any credentials or secrets. These should not be exposed to the user.
     */

    const CLIENT_CONFIGS = [
        'VCSRevision',
        'DeploymentEnvironment',
        'OnPlaya',
        'ReadOnly',
        'RangerPoliciesUrl',
        'JoiningRangerSpecialTeamsUrl',
        'MealInfoAvailable',
        'MealDates',
        'RadioCheckoutFormUrl',
        'SiteNotice',
        'SiteTitle',
        'MotorpoolPolicyEnable',
        'RpTicketThreshold',
        'ScTicketThreshold',
        'YrTicketThreshold',
        'DualClubhouse',
        'ClassicClubhouseUrl',
        'AdminEmail',
        'GeneralSupportEmail',
        'PersonnelEmail',
        'VCEmail',
    ];

    public function show() {
        return response()->json(setting(self::CLIENT_CONFIGS));
    }
}

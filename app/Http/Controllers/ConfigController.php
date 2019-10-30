<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\SqlHelper;

class ConfigController extends Controller
{
    /*
     * WARNING: Do not include a configuration variable that contains
     * any credentials or secrets. These should not be exposed to the user.
     */

    const CLIENT_CONFIGS = [
        'AdminEmail',
        'GeneralSupportEmail',
        'JoiningRangerSpecialTeamsUrl',
        'MealDates',
        'MealInfoAvailable',
        'MotorpoolPolicyEnable',
        'OnPlaya',
        'PersonnelEmail',
        'RadioCheckoutFormUrl',
        'RangerFeedbackFormUrl',
        'RangerPoliciesUrl',
        'ReadOnly',
        'RpTicketThreshold',
        'ScTicketThreshold',
        'TrainingAcademyEmail',
        'VCEmail',
        'YrTicketThreshold',
    ];

    public function show() {
        $configs = setting(self::CLIENT_CONFIGS);

        if (config('clubhouse.GroundhogDayServer')) {
            $configs['GroundhogDayTime'] = (string) SqlHelper::now();
        }

        $configs['DeploymentEnvironment'] = config('clubhouse.DeploymentEnvironment');

        return response()->json($configs);
    }
}

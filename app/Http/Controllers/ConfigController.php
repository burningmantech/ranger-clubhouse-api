<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    /*
     * WARNING: Do not include a configuration variable that contains
     * any credentials or secrets. These should not be exposed to the user.
     */

    const CLIENT_CONFIGS = [
        'AdminEmail',
        'AuditorRegistrationDisabled',
        'EditorUrl',
        'HQWindowInterfaceEnabled',
        'GeneralSupportEmail',
        'JoiningRangerSpecialTeamsUrl',
        'LoginManageOnPlayaEnabled',
        'MealDates',
        'MealInfoAvailable',
        'MentorEmail',
        'MotorpoolPolicyEnable',
        'PersonnelEmail',
        'RadioCheckoutAgreementEnabled',
        'RangerFeedbackFormUrl',
        'RangerManualUrl',
        'RangerPoliciesUrl',
        'RpTicketThreshold',
        'ScTicketThreshold',
        'TrainingAcademyEmail',
        'VCEmail',
    ];

    /**
     * Return the minimal set of settings used to boot the frontend application.
     *
     * @return JsonResponse
     */

    public function show()
    {
        $ghdTime = config('clubhouse.GroundhogDayTime');
        if (!empty($ghdTime)) {
            Carbon::setTestNow($ghdTime);
        }

        $configs = setting(self::CLIENT_CONFIGS);

        if (config('clubhouse.GroundhogDayTime')) {
            $configs['GroundhogDayTime'] = (string)now();
        }

        $configs['DeploymentEnvironment'] = config('clubhouse.DeploymentEnvironment');

        return response()->json($configs);
    }
}

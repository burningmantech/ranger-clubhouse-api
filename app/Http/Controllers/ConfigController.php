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

    const array CLIENT_CONFIGS = [
        'AdminEmail',
        'AuditorRegistrationDisabled',
        'EventManagementOnPlayaEnabled',
        'GoogleAnalyticsID',
        'HQWindowInterfaceEnabled',
        'JoiningRangerSpecialTeamsUrl',
        'MealDates',
        'MealInfoAvailable',
        'MentorEmail',
        'PersonnelEmail',
        'RangerFeedbackFormUrl',
        'RangerManualUrl',
        'RangerPoliciesUrl',
        'ScTicketThreshold',
        'SpTicketThreshold',
        'TrainingAcademyEmail',
        'VCEmail',
    ];

    /**
     * Return the minimal set of settings used to boot the frontend application.
     *
     * @return JsonResponse
     */

    public function show(): JsonResponse
    {
        $ghdTime = config('clubhouse.GroundhogDayTime');
        if (!empty($ghdTime)) {
            Carbon::setTestNow($ghdTime);
        }

        $configs = setting(self::CLIENT_CONFIGS);

        if (config('clubhouse.GroundhogDayTime')) {
            $configs['GroundhogDayTime'] = (string)now();
            $configs['DatabaseCreatedOn'] = setting('DatabaseCreatedOn');
        }

        $configs['DeploymentEnvironment'] = config('clubhouse.DeploymentEnvironment');

        return response()->json($configs);
    }

    /**
     * Obtain the current dashboard period
     *
     * @return JsonResponse
     */

    public function dashboardPeriod() : JsonResponse
    {
        return response()->json([ 'period' => setting('DashboardPeriod') ]);
    }
}

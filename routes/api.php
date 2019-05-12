<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
*/


/*
 * API which do not require an authorized user
 */


Route::group([
    'middleware' => 'api',
], function($router) {
    Route::get('config', 'ConfigController@show');

    Route::post('auth/login', 'AuthController@login');
    Route::post('auth/reset-password', 'AuthController@resetPassword');

    Route::post('person/register', 'PersonController@register');

    Route::post('error-log/record', 'ErrorLogController@record');
    Route::post('action-log/record', 'ActionLogController@record');
});


/*
 * API which require an authorized user
 */

Route::group([
    'middleware' => [ 'api', 'auth' ],
], function ($router) {

    Route::post('auth/logout', 'AuthController@logout');
    Route::post('auth/refresh', 'AuthController@refresh');

    Route::resource('alert', 'AlertController');

    Route::get('access-document/current', 'AccessDocumentController@current');
    Route::get('access-document/expiring', 'AccessDocumentController@expiring');
    Route::patch('access-document/{access_document}/status', 'AccessDocumentController@status');
    Route::resource('access-document', 'AccessDocumentController');

    Route::resource('access-document-delivery', 'AccessDocumentDeliveryController');

    Route::resource('action-log', 'ActionLogController', [ 'only' => 'index' ]);

    Route::post('asset/checkout', 'AssetController@checkout');
    Route::get('asset/{asset}/history', 'AssetController@history');
    Route::post('asset/{asset}/checkin', 'AssetController@checkin');
    Route::resource('asset', 'AssetController');
    Route::resource('asset-attachment', 'AssetAttachmentController');

    Route::get('asset-person/radio-checkout-report', 'AssetPersonController@radioCheckoutReport');
    Route::post('asset-person/checkout', 'AssetPersonController@checkout');
    Route::post('asset-person/{asset_person}/checkin', 'AssetPersonController@checkin');
    Route::resource('asset-person', 'AssetPersonController');

    Route::post('bmid/lambase', 'BmidController@lambase');
    Route::get('bmid/manage', 'BmidController@manage');
    Route::get('bmid/manage-person', 'BmidController@managePerson');
    Route::get('bmid/sanity-check', 'BmidController@sanityCheck');
    Route::resource('bmid', 'BmidController');

    Route::get('broadcast/messages', 'BroadcastController@messages');

    Route::post('bulk-upload', 'BulkUploadController@update');

    Route::get('callsigns', 'CallsignsController@index');
    Route::get('handles', 'HandleController@index');

    Route::get('contact/log', 'ContactController@showLog');
    Route::post('contact/send', 'ContactController@send');

    Route::get('debug/sleep-test', 'DebugController@sleepTest');
    Route::get('debug/db-test', 'DebugController@dbTest');

    Route::get('language/speakers', 'LanguageController@speakers');
    Route::resource('language', 'LanguageController');

    Route::delete('error-log/purge', 'ErrorLogController@purge');
    Route::resource('error-log', 'ErrorLogController', [ 'only' => 'index' ]);

    Route::get('event-dates/year', 'EventDatesController@showYear');
    Route::resource('event-dates', 'EventDatesController');

    Route::resource('manual-review', 'ManualReviewController@passed');

    Route::patch('messages/{person_message}/markread', 'PersonMessageController@markread');
    Route::resource('messages', 'PersonMessageController', [ 'only' => [ 'index', 'store', 'destroy' ]]);

    Route::get('mentor/mentees', 'MentorController@mentees');

    Route::resource('motd', 'MotdController');

    Route::get('person/alpha-shirts', 'PersonController@alphaShirts');

    Route::get('person/{person}/alerts', 'AlertPersonController@index');
    Route::patch('person/{person}/alerts', 'AlertPersonController@update');

    Route::get('person/{person}/mentees', 'PersonController@mentees');
    Route::get('person/{person}/mentors', 'PersonController@mentors');
    Route::get('person/{person}/credits', 'PersonController@credits');
    Route::get('person/{person}/schedule/permission', 'PersonScheduleController@permission');
    Route::get('person/{person}/schedule/imminent', 'PersonScheduleController@imminent');
    Route::get('person/{person}/schedule/expected', 'PersonScheduleController@expected');
    Route::resource('person/{person}/schedule', 'PersonScheduleController', [ 'only' => [ 'index', 'store', 'destroy' ]]);

    Route::get('person/{person}/positions', 'PersonController@positions');
    Route::post('person/{person}/positions', 'PersonController@updatePositions');
    Route::get('person/{person}/photo', 'PersonController@photo');
    Route::patch('person/{person}/password', 'PersonController@password');
    Route::get('person/{person}/roles', 'PersonController@roles');
    Route::post('person/{person}/roles', 'PersonController@updateRoles');

    Route::get('person/{person}/user-info', 'PersonController@userInfo');
    Route::get('person/{person}/unread-message-count', 'PersonController@UnreadMessageCount');
    Route::get('person/{person}/event-info', 'PersonController@eventInfo');

    Route::resource('person', 'PersonController', [ 'only' => [ 'index','show','store','update','destroy' ]]);

    Route::resource('position-credit', 'PositionCreditController');
    Route::post('position-credit/copy', 'PositionCreditController@copy');
    Route::resource('position', 'PositionController');

    Route::resource('role', 'RoleController');

    Route::get('salesforce/config', 'SalesforceController@config');
    Route::get('salesforce/import', 'SalesforceController@import');

    Route::resource('setting', 'SettingController');

    Route::get('slot/dirt-shift-times', 'SlotController@dirtShiftTimes');
    Route::get('slot/shift-lead-report', 'SlotController@shiftLeadReport');
    Route::get('slot/years', 'SlotController@years');
    Route::get('slot/{slot}/people', 'SlotController@people');
    Route::patch('slot/bulkupdate', 'SlotController@bulkUpdate');
    Route::resource('slot', 'SlotController');

    Route::get('sms', 'SmsController@getNumbers');
    Route::post('sms', 'SmsController@updateNumbers');
    Route::post('sms/send-code', 'SmsController@sendNewCode');
    Route::post('sms/confirm-code', 'SmsController@confirmCode');


    Route::get('training-session/sessions', 'TrainingSessionController@sessions');
    Route::get('training-session/{id}', 'TrainingSessionController@show');
    Route::post('training-session/{id}/score', 'TrainingSessionController@score');

    Route::get('training/{id}/multiple-enrollments', 'TrainingController@multipleEnrollmentsReport');
    Route::get('training/{id}/capacity', 'TrainingController@capacityReport');
    Route::get('training/{id}/people-training-completed', 'TrainingController@peopleTrainingCompleted');
    Route::get('training/{id}/untrained-people', 'TrainingController@untrainedPeopleReport');
    Route::get('training/{id}', 'TrainingController@show');

    Route::get('ticketing/info', 'TicketingController@ticketingInfo');
    Route::get('ticketing/{person}/package', 'TicketingController@package');
    Route::post('ticketing/{person}/delivery', 'TicketingController@delivery');
    Route::patch('ticketing/{person}/wapso', 'TicketingController@storeWAPSO');

    Route::post('timesheet/bulk-sign-in-out', 'TimesheetController@bulkSignInOut');
    Route::get('timesheet/correction-requests', 'TimesheetController@correctionRequests');
    Route::post('timesheet/confirm', 'TimesheetController@confirm');
    Route::get('timesheet/freaking-years', 'TimesheetController@freakingYearsReport');
    Route::get('timesheet/info', 'TimesheetController@info');
    Route::get('timesheet/log', 'TimesheetController@showLog');
    Route::post('timesheet/signin', 'TimesheetController@signin');
    Route::get('timesheet/radio-eligibility', 'TimesheetController@radioEligibilityReport');
    Route::get('timesheet/shirts-earned', 'TimesheetController@shirtsEarnedReport');
    Route::get('timesheet/unconfirmed-people', 'TimesheetController@unconfirmedPeople');
    Route::post('timesheet/{timesheet}/signoff', 'TimesheetController@signoff');
    Route::resource('timesheet', 'TimesheetController');

    Route::resource('timesheet-missing', 'TimesheetMissingController');

    Route::resource('help', 'HelpController');
});

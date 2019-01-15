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
    Route::get('access-document/sowap', 'AccessDocumentController@retrieveSOWAP');
    Route::patch('access-document/sowap', 'AccessDocumentController@storeSOWAP');
    Route::get('access-document/ticketing-info', 'AccessDocumentController@ticketingInfo');

    Route::patch('access-document/{access_document}/status', 'AccessDocumentController@status');
    Route::resource('access-document', 'AccessDocumentController');

    Route::resource('access-document-delivery', 'AccessDocumentDeliveryController');


    Route::resource('asset', 'AssetController');
    Route::resource('asset-attachment', 'AssetAttachmentController');

    Route::post('asset-person/checkout', 'AssetPersonController@checkout');
    Route::post('asset-person/{asset_person}/checkin', 'AssetPersonController@checkin');
    Route::resource('asset-person', 'AssetPersonController');

    Route::get('broadcast/messages', 'BroadcastController@messages');

    Route::get('callsigns', 'CallsignsController@index');
    Route::get('handles', 'HandleController@index');

    Route::get('contact/log', 'ContactController@showLog');
    Route::post('contact/send', 'ContactController@send');

    Route::get('language/speakers', 'LanguageController@speakers');
    Route::resource('language', 'LanguageController');

    Route::resource('manual-review', 'ManualReviewController@passed');

    Route::patch('messages/{person_message}/markread', 'PersonMessageController@markread');
    Route::resource('messages', 'PersonMessageController', [ 'only' => [ 'index', 'store', 'destroy' ]]);

    Route::get('person/{person}/alerts', 'AlertPersonController@index');
    Route::patch('person/{person}/alerts', 'AlertPersonController@update');

    Route::get('person/{person}/mentees', 'PersonController@mentees');
    Route::get('person/{person}/mentors', 'PersonController@mentors');
    Route::get('person/{person}/credits', 'PersonController@credits');
    Route::get('person/{person}/schedule/permission', 'PersonScheduleController@permission');
    Route::resource('person/{person}/schedule', 'PersonScheduleController', [ 'only' => [ 'index', 'store', 'destroy' ]]);

    Route::get('person/{person}/positions', 'PersonController@positions');
    Route::post('person/{person}/positions', 'PersonController@updatePositions');
    Route::get('person/{person}/photo', 'PersonController@photo');
    Route::patch('person/{person}/password', 'PersonController@password');
    Route::get('person/{person}/roles', 'PersonController@roles');
    Route::post('person/{person}/roles', 'PersonController@updateRoles');

    Route::get('person/{person}/teacher', 'PersonController@teacher');
    Route::get('person/{person}/unread-message-count', 'PersonController@UnreadMessageCount');
    Route::get('person/{person}/yearinfo', 'PersonController@yearInfo');
    Route::get('person/{person}/years', 'PersonController@years');

    Route::resource('person', 'PersonController', [ 'only' => [ 'index','show','store','update','destroy' ]]);

    Route::resource('position-credit', 'PositionCreditController');
    Route::resource('position', 'PositionController');

    Route::resource('role', 'RoleController');

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

    Route::post('timesheet/confirm', 'TimesheetController@confirm');
    Route::post('timesheet/signin', 'TimesheetController@signin');
    Route::get('timesheet/log', 'TimesheetController@showLog');
    Route::get('timesheet/correction-requests', 'TimesheetController@correctionRequests');
    Route::get('timesheet/unconfirmed-people', 'TimesheetController@unconfirmedPeople');
    Route::post('timesheet/{timesheet}/signoff', 'TimesheetController@signoff');
    Route::get('timesheet/info', 'TimesheetController@info');
    Route::resource('timesheet', 'TimesheetController');

    Route::resource('timesheet-missing', 'TimesheetMissingController');
});

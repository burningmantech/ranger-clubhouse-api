<?php

namespace App\Http\Controllers;

use App\Lib\RBS;
use App\Lib\SMSException;
use App\Lib\SMSService;
use App\Models\Broadcast;
use App\Models\BroadcastMessage;
use App\Models\ErrorLog;
use App\Models\Person;
use App\Models\Role;
use App\Models\Schedule;
use Carbon\Carbon;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use App\Exceptions\UnacceptableConditionException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Exceptions\RestException;
use Twilio\Exceptions\TwilioException;

/*
 * Handle setting and verify the SMS numbers for a person
 *
 */

class SmsController extends ApiController
{
    /**
     * Retrieve the SMS numbers, verification and stop states for a person
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function getNumbers(): JsonResponse
    {
        $params = request()->validate([
            'person_id' => 'required|integer'
        ]);

        $person = $this->findPerson($params['person_id']);

        $this->allowedToSMS($person);

        return response()->json(['numbers' => self::buildSmsResponse($person)]);
    }

    /**
     * Update a person's SMS numbers and send a verification code if changed.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws RestException
     * @throws ConfigurationException
     * @throws TwilioException
     */

    public function updateNumbers(): JsonResponse
    {
        $params = request()->validate([
            'person_id' => 'required|integer',
            'on_playa' => 'present',
            'off_playa' => 'present'
        ]);

        prevent_if_ghd_server('SMS numbers update');

        $person = $this->findPerson($params['person_id']);

        $this->allowedToSMS($person);

        $onPlaya = self::validatePhoneNumber($person, $params['on_playa']);
        $offPlaya = self::validatePhoneNumber($person, $params['off_playa']);

        $isSame = $onPlaya == $offPlaya;

        $person->sms_on_playa = $onPlaya;
        $person->sms_off_playa = $offPlaya;

        $onPlayaChanged = $person->isDirty('sms_on_playa');
        $offPlayaChanged = $person->isDirty('sms_off_playa');

        /*
         * If one number changed and now matches an existing number, copy over
         * verification and stopped status
         */

        if ($offPlayaChanged && !$onPlayaChanged && $onPlaya == $offPlaya) {
            $person->sms_off_playa_verified = $person->sms_on_playa_verified;
            $person->sms_off_playa_stopped = $person->sms_on_playa_stopped;
        } elseif ($onPlayaChanged && !$offPlayaChanged && $onPlaya == $offPlaya) {
            $person->sms_on_playa_verified = $person->sms_off_playa_verified;
            $person->sms_on_playa_stopped = $person->sms_off_playa_stopped;
        } else {
            if ($onPlayaChanged) {
                $person->sms_on_playa_verified = false;
                $person->sms_on_playa_stopped = false;
            }

            if ($offPlayaChanged) {
                $person->sms_off_playa_verified = false;
                $person->sms_off_playa_stopped = false;
            }
        }

        $onPlayaStatus = 'none';

        // Check to see either number is a text capable device.
        if ($onPlayaChanged && $onPlaya != '' && !$person->sms_on_playa_verified) {
            if (!SMSService::isSMSCapable($onPlaya)) {
                throw new UnacceptableConditionException("Sorry, $onPlaya does not appear to be a cellphone.");
            }
        }

        if ($offPlayaChanged && $offPlaya != '' && $onPlaya != $offPlaya && !$person->sms_off_playa_verified) {
            if (!SMSService::isSMSCapable($offPlaya)) {
                throw new UnacceptableConditionException("Sorry, $offPlaya does not appear to be a cellphone.");
            }
        }


        if ($onPlayaChanged && $onPlaya != '' && !$person->sms_on_playa_verified) {
            if ($this->sendVerificationCode($person, 'on_playa')) {
                $onPlayaStatus = 'sent';
            } else {
                $onPlayaStatus = 'sent-fail';
            }
            if ($onPlaya == $offPlaya) {
                $person->sms_off_playa_code = $person->sms_on_playa_code;
            }
        }

        $offPlayaStatus = 'none';
        if ($offPlayaChanged && $offPlaya != '' && $onPlaya != $offPlaya && !$person->sms_off_playa_verified) {
            if ($this->sendVerificationCode($person, 'off_playa')) {
                $offPlayaStatus = 'sent';
            } else {
                $offPlayaStatus = 'sent-fail';
            }
        }

        $person->saveWithoutValidation();

        $response = [
            'status' => 'ok',
            'numbers' => self::buildSMSResponse($person)
        ];

        $response['numbers']['on_playa']['code_status'] = $onPlayaStatus;
        $response['numbers']['off_playa']['code_status'] = $offPlayaStatus;

        return response()->json($response);
    }

    /**
     * Verify a number based on a sent code
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function confirmCode(): JsonResponse
    {
        $params = request()->validate([
            'person_id' => 'required|integer',
            'type' => 'required|string',
            'code' => 'required|string'
        ]);

        $person = $this->findPerson($params['person_id']);
        $this->allowedToSMS($person);

        $type = $params['type'];

        if ($type == 'off-playa') {
            $phone = $person->sms_off_playa;
            $verified = $person->sms_off_playa_verified;
            $code = $person->sms_off_playa_code;
        } else {
            $phone = $person->sms_on_playa;
            $verified = $person->sms_on_playa_verified;
            $code = $person->sms_on_playa_code;
        }

        $response = ['numbers' => self::buildSMSResponse($person)];

        // Bail if the phone has already been verified
        if ($verified) {
            $response['status'] = 'already-verified';
            return response()->json($response);
        }

        // Make sure the codes match.
        if ($code != $params['code']) {
            $response['status'] = 'no-match';
            return response()->json($response);
        }

        if ($person->sms_off_playa == $person->sms_on_playa) {
            $person->sms_on_playa_verified = true;
            $person->sms_off_playa_verified = true;
        } elseif ($type == 'off-playa') {
            $person->sms_off_playa_verified = true;
        } else {
            $person->sms_on_playa_verified = true;
        }

        $person->saveWithoutValidation();

        return response()->json([
            'status' => 'confirmed',
            'numbers' => self::buildSMSResponse($person)
        ]);
    }

    /**
     * Send a new verification code.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function sendNewCode(): JsonResponse
    {
        $params = request()->validate([
            'person_id' => 'required|integer',
            'type' => 'required|string',
        ]);

        prevent_if_ghd_server('SMS verification');

        $person = $this->findPerson($params['person_id']);

        $this->allowedToSMS($person);

        if ($params['type'] == 'off-playa') {
            $phone = $person->sms_off_playa;
            $verified = $person->sms_off_playa_verified;
            $column = 'off_playa';
        } else {
            $phone = $person->sms_on_playa;
            $verified = $person->sms_on_playa_verified;
            $column = 'on_playa';
        }

        // Punt if no phone is there
        if (empty($phone)) {
            return response()->json(['status' => 'no-number']);
        }

        // Already verified?
        if ($verified) {
            return response()->json(['status' => 'already-verified']);
        }

        if ($this->sendVerificationCode($person, $column)) {
            $status = 'sent';
        } else {
            $status = 'sent-fail';
        }

        $person->saveWithoutValidation();

        return response()->json(['status' => $status]);
    }

    /**
     * Process an incoming message
     * @return Application|ResponseFactory|JsonResponse|Response
     */

    public function inbound(): Response|JsonResponse|Application|ResponseFactory
    {
        $ua = request()->userAgent();
        if (preg_match("/(AOLBuild|Baidu|bingbot|DuckDuckBot|googlebot|yahoo|yandex)/i", $ua)) {
            // How did bingbot get their grubby digital dirty paws on this unpublished url?
            return response()->json([ 'error' => 'not authorized.'], 403);
        }

        try {
            $incoming = SMSService::processIncoming(request());
        } catch (Exception $e) {
            ErrorLog::recordException($e, 'sms-inbound-exception');
            return response()->json(['status' => 'error'], 500);
        }

        // Try to find who the number belongs to
        $number = $incoming->phone;
        $message = $incoming->message;

        if (empty($number)) {
            ErrorLog::record('sms-inbound-exception', ['type' => 'inbound-malformed']);
            return response('Missing phone number?', 422);
        }

        $person = Person::where('sms_on_playa', $number)->orWhere('sms_off_playa', $number)->first();
        if (!$person) {
            // Who are you?
            $reply = "Hello from the Black Rock Ranger Clubhouse Bot. Sorry, your phone number cannot be found in the database. Contact rangers@burningman.org for help.";
            BroadcastMessage::record(null, Broadcast::STATUS_UNKNOWN_PHONE, null, 'sms', $number, 'inbound', $message);
            return SMSService::replyResponse($reply);
        }

        $personId = $person->id;
        $onPlaya = ($person->sms_on_playa == $number);
        $both = ($person->sms_on_playa == $person->sms_off_playa);


        switch (strtolower(trim($message))) {
            // The industry recognized stop-talking-to-me-damn-you commands.
            case 'stop':
            case 'quit':
            case 'cancel':
            case 'end':
            case 'arret':      // Vive La France!
            case 'unsubscribe':
            case 'unsub':
                $status = Broadcast::STATUS_STOP;

                // Flag the sms numbers as stopped
                self::updateStoppedNumbers($person, true, $both ? 2 : $onPlaya);

                // Some services may respond automatically to a STOP and not
                // allow a response from the application
                if (SMSService::NO_STOP_REPLIES) {
                    $reply = '';
                } else {
                    $reply = 'You will no longer receive alerts. Reply START to resubscribe.';
                }
                break;

            // Restart the sms numbers
            case 'start':
            case 'unstop':
            case 'sub':
            case 'subscribe':
            case 'yes':
                $status = Broadcast::STATUS_START;
                self::updateStoppedNumbers($person, false, $both ? 2 : $onPlaya);
                $reply = 'You have been re-subscribed to Clubhouse alerts.';
                break;

            // Some services may not pass this command through (*cough* Twilio *cough*)
            case 'help':
                $status = Broadcast::STATUS_HELP;
                $reply = 'Commands available: STOP, START, HELP. Contact rangers@burningman.org for more help.';
                break;

            // Status, only ADMIN accounts may use this.
            case 'stats':
                $isAdmin = $person->hasRole(Role::ADMIN);
                if ($isAdmin) {
                    $status = Broadcast::STATUS_STATS;
                    $rows = Broadcast::whereRaw("created_at >= DATE_SUB(?, INTERVAL 1 DAY)", [now()])->get();
                    $broadcastCount = $rows->count();
                    $smsFails = 0;
                    $emailFails = 0;
                    $totalRecipients = 0;
                    foreach ($rows as $row) {
                        $totalRecipients += $row->recipient_count;
                        $smsFails += $row->sms_failed;
                        $emailFails += $row->email_failed;
                    }

                    if ($broadcastCount == 0) {
                        $reply = "All quiet. No broadcasts sent in the last 24 hours.";
                    } else {
                        $lastRow = $rows->last();
                        $lastDate = $lastRow->created_at;
                        $reply = "Broadcasts $broadcastCount Recipients $totalRecipients SMS fails $smsFails Email fails $emailFails Last broadcast @ $lastDate";
                    }

                    $inboundCount = BroadcastMessage::where('direction', 'inbound')->whereRaw('created_at >= DATE_SUB(?, INTERVAL 1 DAY)', [now()])->count();

                    $reply .= " Inbound msgs $inboundCount";
                } else {
                    $reply = 'Sorry, you are not permitted to use this command.';
                }
                break;

            // Show the next shift for the person
            case 'next':
                $nextShift = Schedule::findNextShift($personId);
                if ($nextShift) {
                    $formatted = Carbon::parse($nextShift->begins)->format('l M d Y @ H:i');
                    $reply = "Next shift is {$nextShift->title} - {$nextShift->description} {$formatted}";
                } else {
                    $reply = "No upcoming shifts were found.";
                }
                $status = Broadcast::STATUS_NEXT;
                break;

            default:
                $status = Broadcast::STATUS_UNKNOWN_COMMAND;
                $reply = "The command is not understood. Contact rangers@burningman.org for help.";
                break;
        }

        // Record the message
        BroadcastMessage::record(null, $status, $personId, 'sms', $number, 'inbound', $message);
        // .. and record the reply
        if ($reply != '') {
            BroadcastMessage::record(null, Broadcast::STATUS_REPLY, $personId, 'sms', $number, 'outbound', $reply);
            return SMSService::replyResponse(RBS::SMS_PREFIX . $reply);
        }
        return SMSService::replyResponse('');
    }

    /**
     * Build an API response for both numbers, their verification & stopped statuses
     * and if both numbers are the same.
     *
     * @param Person $person person record to build response from
     * @return array
     */

    public static function buildSMSResponse(Person $person): array
    {
        return [
            'off_playa' => [
                'phone' => $person->sms_off_playa,
                'is_stopped' => $person->sms_off_playa_stopped,
                'is_verified' => $person->sms_off_playa_verified
            ],
            'on_playa' => [
                'phone' => $person->sms_on_playa,
                'is_stopped' => $person->sms_on_playa_stopped,
                'is_verified' => $person->sms_on_playa_verified
            ],

            'is_same' => ($person->sms_off_playa == $person->sms_on_playa)
        ];
    }

    /**
     * Validate, format a phone as E.164 standard, and verify the number
     * does not belong to another account.
     *
     * @param Person $person person number is allowed to belong to
     * @param string $phone the phone number
     * @return string the formatted phone number
     * @throw \InvalidArgumentException if validation fails, or is used by another account.
     */

    private static function validatePhoneNumber($person, $phone): string
    {
        // Eliminate every char except 0-9 and +
        $phone = preg_replace("/[^0-9+]/", '', $phone);

        // nothing there
        if (empty($phone)) {
            return '';
        }

        $len = strlen($phone);
        if (str_starts_with($phone, '+')) {
            if ($person->country == 'US' && $len == 11 && substr($phone, 1, 1) != '1') {
                // add +1 to the number
                $phone = '+1' . substr($phone, 1);
            }
        } else {
            // Only area code & number given, add the USA/Canadian +1 country code.
            if ($len == 10) {
                $phone = '+1' . $phone;
            } elseif (str_starts_with($phone, '1') && $len == 11) {
                // Assume USA or Canadian number, add the +
                $phone = '+' . $phone;
            }
        }

        // Number has to be at least the area code and number.
        if (strlen($phone) < 10) {
            throw new UnacceptableConditionException("Number is too short");
        }

        // Normalization should have added a + for US/Canada.
        if (!str_starts_with($phone, '+')) {
            throw new UnacceptableConditionException("SMS number should begin with a '+' for International numbers.");
        }

        /**
         * Start July 3, 2023 - the UK requires a UK alpha sender id or UK domestic number to send to. Revisit this
         * if we wanna go down the RBS restructuring rabbit to support multiple sender ids.
         */

        $util = PhoneNumberUtil::getInstance();
        try {
            $parsedPhone = $util->parse($phone, 'US');
            if ($util->getRegionCodeForNumber($parsedPhone) == 'GB' || str_starts_with($phone, '+44')) {
                throw new UnacceptableConditionException("Sorry, United Kingdom phone numbers cannot be used.");
            }
        } catch (NumberParseException $e) {
            // ignore
        }


        /*
         * Ensure the number does not belong to someone else.
         */

        $exists = Person::where('id', '!=', $person->id)->where(function ($q) use ($phone) {
            $q->where('sms_on_playa', $phone)->orWhere('sms_off_playa', $phone);
        })->exists();

        if ($exists) {
            throw new UnacceptableConditionException("Phone number is used by another account");
        }

        return $phone;
    }

    /**
     * Send a SMS verification code to a phone.
     *
     * @param Person $person person record to use
     * @param string $column which column the number is in ('off_playa', 'on_playa')
     * @return bool
     */

    private function sendVerificationCode($person, $column): bool
    {
        $phone = $person['sms_' . $column];
        $code = self::generateVerifyCode();

        $message = "Your verification code is: $code";
        try {
            SMSService::broadcast([$phone], $message);
        } catch (SMSException $e) {
            ErrorLog::recordException($e, 'sms-exception', [
                'type' => 'sms-verify',
                'person_id' => $person->id,
                'phone' => $phone
            ]);
            return false;
        }

        if ($person->sms_off_playa == $person->sms_on_playa) {
            $person->sms_on_playa_code = $person->sms_off_playa_code = $code;
        } else {
            $person['sms_' . $column . '_code'] = $code;
        }

        BroadcastMessage::record(null, Broadcast::STATUS_VERIFY, $person->id, 'sms', $phone, 'outbound', $message);

        return true;
    }

    /**
     * Generate a simple numeric verification code
     * @return string
     */

    private static function generateVerifyCode(): string
    {
        $code = '';

        for ($i = 0; $i < 4; $i++) {
            $code .= mt_rand(0, 9);
        }

        return $code;
    }

    /**
     * @param Person $person
     * @return void
     * @throws AuthorizationException
     */

    private function allowedToSMS(Person $person): void
    {
        /*
         * Only Admins are allowed to deal with another number.
         */

        if ($person->id != $this->user->id && !$this->userHasRole(Role::ADMIN)) {
            $this->notPermitted("Not authorized.");
        }
    }

    /**
     * Update the STOP request for a person
     *
     * @param Person $person to update
     * @param bool $stop flag to set
     * @param int $which number to update 0: pre-event, 1: on playa, 2: both
     */

    private static function updateStoppedNumbers($person, $stop, $which): void
    {
        switch ($which) {
            case 0:
                $person->sms_off_playa_stopped = $stop;
                break;
            case 1:
                $person->sms_on_playa_stopped = $stop;
                break;
            default:
                $person->sms_on_playa_stopped = $stop;
                $person->sms_off_playa_stopped = $stop;
                break;
        }

        $person->saveWithoutValidation();
    }
}

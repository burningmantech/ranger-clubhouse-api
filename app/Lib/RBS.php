<?php

/*
 * The Ranger Broadcasting Service
 */

namespace App\Lib;

use App\Helpers\SqlHelper;

use App\Models\Broadcast;
use App\Models\BroadcastMessage;

use App\Models\Alert;
use App\Models\AlertPerson;
use App\Models\ErrorLog;
use App\Models\Person;
use App\Models\PersonMessage;
use App\Models\Position;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

use App\Mail\ClubhouseNewMessageMail;
use App\Mail\RBSMail;

class RBS
{
    /*
     * Positions which may have frequenty shift muster requests.
     */
    const FREQUENT_MUSTER_POSITIONS = [
        Position::RSC_SHIFT_LEAD,
        Position::HQ_WINDOW,
        Position::DIRT_GREEN_DOT,
        Position::GREEN_DOT_LEAD,
        Position::TROUBLESHOOTER,
        Position::DOUBLE_OH_7
    ];

    /*
     * Attributes describes each broadcast type.
     *
     * The follow keys help define what critera a broadcast uses/requires:
     *
     * is_simple - only takes an sms sized message, may broadcast to SMS and/or email
     * sms_only - only broadcasts to SMS, used with is_simple
     * has_status - requires a person status (active, inactive, prospective, etc)
     * has_restrictions - uses training, on_playa, and on_duty, attending
     * has_position - uses a position to broadcast to, used with has_restrictions
     * has_muster_position - broadcast to position that has on-playa sign ups.
     * alerts (array) - which alerts to use for broadcast
     * alert_id - which alert pref to use, used with is_simple
     * has_slot - uses a slot's sign ups to broadcast
     *
     */


    const ATTRIBUTES = [
        Broadcast::TYPE_GENERAL => [
            'has_status'    => true,
            'has_restrictions' => true,
        ],

        Broadcast::TYPE_POSITION => [
            'has_status'    => true,
            'has_position'  => true,
            'alerts' => [ Alert::TRAINING, Alert::SHIFT_CHANGE, Alert::SHIFT_MUSTER, Alert::SHIFT_CHANGE_PRE_EVENT, Alert::SHIFT_MUSTER_PRE_EVENT ]
        ],

        Broadcast::TYPE_SLOT => [
            'has_slot'  => true,
            'alerts'   => [ Alert::TRAINING, Alert::SHIFT_CHANGE, Alert::SHIFT_CHANGE_PRE_EVENT ]
        ],

        /*
         * TYPE_EDIT_SLOT is special case since the form is hit from the Edit Slot page
         * when a user updates the times for a slot or deletes a slot.
         */

        Broadcast::TYPE_SLOT_EDIT => [
            'alerts'  => [ Alert::TRAINING, Alert::SHIFT_CHANGE, Alert::SHIFT_CHANGE_PRE_EVENT ],
        ],

        /*
         * Start of Simple Broadcasts
         *
         * These will only accept a SMS sized message. The email subject is set
         * the message. No Clubhouse message created.
         */

         Broadcast::TYPE_ONSHIFT => [
             'alert_id' => Alert::ON_SHIFT,
             'is_simple'   => true,
             'sms_only' => true,
         ],

         Broadcast::TYPE_RECRUIT_DIRT => [
             'alert_id'   => Alert::SHIFT_MUSTER,
             'is_simple' => true,
         ],

        Broadcast::TYPE_RECRUIT_POSITION => [
            'alert_id'            => Alert::SHIFT_MUSTER,
            'is_simple'           => true,
            'has_muster_position' => true
        ],

        Broadcast::TYPE_EMERGENCY => [
            'alert_id'   => Alert::EMEREGENCY_BROADCAST,
            'is_simple' => true,
        ],
    ];

    /*
     * Services like Twilio can send a longer message, however it will
     * be broken up into multiple 160 character segments and the account charged
     * for each segment.
     */

    // Prefix and Suffix added to most SMS messages.
    const SMS_PREFIX = ''; // No prefix currently
    const SMS_SUFFIX = ' TXT STOP to unsub'; // 18 characters
    const SMS_LIMIT  = 142; // 160 sms limit - length(SMS_PREFIX + SMS_SUFFIX)


    /*
     * Send an SMS message to list of people
     *
     * @param array $alert the alert row
     * @param int $broadcastId the broadcast identifier
     * @param int $senderId person who sending
     * @param array $people list to send to
     * @param string $message SMS text
     * @return array list of results
     */

    public static function broadcastSMS($alert, $broadcastId, $senderId, $people, $message)
    {
        // Figure out which number to send to
        if ($alert->on_playa) {
            $smsColumn = 'sms_on_playa';
        } else {
            $smsColumn = 'sms_off_playa';
        }

        $stopColumn = $smsColumn.'_stopped';
        $verifiedColumn = $smsColumn . '_verified';

        $phoneNumbers = [];
        $recipients = [];

        $isEmergency = ($alert->id == Alert::EMEREGENCY_BROADCAST);

        // Find the phone numbers to broadcast to
        foreach ($people as $person) {
            $phone = $person->$smsColumn;
            // Skip if the person has not phone number or
            // has not set SMS as a delivery mechanism
            if (empty($phone)) {
                $person->sms_status = 'no-phone';
                continue;
            }

            if (!$person->use_sms && !$isEmergency) {
                $person->sms_status = 'no-contact';
                continue;
            }

            // The number selected has to be verified and not stopped.
            if ($person->$stopColumn) {
                $person->sms_status = 'stopped';
                continue;
            }
            if (!$person->$verifiedColumn) {
                $person->sms_status = 'unverified';
                continue;
            }

            $phoneNumbers[] = $phone;
            $recipients[] = (object)[
                'person' => $person,
                'phone'  => $phone,
            ];
        }

        if (count($phoneNumbers) > 0) {
            /*
             * Fire away! Annoy the people for fun and profit...
             */

            if (!setting('BroadcastSMSSandbox')) {
                try {
                    $status = SMSService::broadcast($phoneNumbers, $message);
                } catch (SMSException $e) {
                    // meh - usually the Internet is spotty
                    ErrorLog::recordException($e, 'sms-exception', [
                            'type'          => 'broadcast',
                            'broadcast_id'  => $broadcastId,
                            'phone_numbers' => $phoneNumbers
                     ]);
                    $status = Broadcast::STATUS_SERVICE_FAIL;
                }
            } else {
                $status = Broadcast::STATUS_SENT;
            }

            // Log each message with the status
            foreach ($recipients as $recipient) {
                BroadcastMessage::record($broadcastId, $status, $recipient->person->id, 'sms', $recipient->phone, 'outbound');
                $recipient->person->sms_status = $status;
            }
        }
    }

    /*
     * Send an Email message to list of people
     *
     * @param array $alert the alert row
     * @param int $broadcastId the broadcast identifier
     * @param int $senderId person who sending
     * @param array $people list to send to
     * @param string $from email address of sender (usually do-not-reply@burningman.org)
     * @param string $subject email subject
     * @param string $message email body
     * @return array list of results
     */

    public static function broadcastEmail($alert, $broadcastId, $senderId, $people, $from, $subject, $message)
    {
        $sandbox = setting('BroadcastMailSandbox');


        if (!$sandbox) {
            // Wrap the message in an HTML email template
            $body = (new RBSMail($subject, $message, $alert))->render();
            list($mailer, $emailMessage) = self::setupSMTP($from, $subject, $body);
        }

        $results = [];
        $hasFailed = false;
        $force = ($alert->id == Alert::EMEREGENCY_BROADCAST);

        foreach ($people as $person) {
            // Skip if user has not set email as a delivery mechansim
            if (!$person->use_email && !$force) {
                $person->email_status = 'no-contact';
                continue;
            }

            $email = $person->email;
            $personId = $person->id;

            if ($sandbox) {
                // In sandbox mode.. don't send anything.
                $status = 'sent';
            } else {
                // Sender format is "Callsign (Real Name)"
                $to = [ $email => $person->callsign.' ('.$person->first_name.' '.$person->last_name.')'];
                $emailMessage->setTo($to);
                try {
                    if ($mailer->send($emailMessage)) {
                        $status = Broadcast::STATUS_SENT;
                    } else {
                        $status = Broadcast::STATUS_SERVICE_FAIL;
                    }
                } catch (\Swift_TransportException $e) {
                    $status = Broadcast::STATUS_SERVICE_FAIL;
                    ErrorLog::recordException($e, 'email-exception', [
                            'type'                 => 'broadcast',
                            'broadcast_id'         => $broadcastId,
                            'email'                => $email,
                            'person_id'            => $person->id
                     ]);
                }
            }

            $person->email_status = $status;
            BroadcastMessage::record($broadcastId, $status, $personId, 'email', $email, 'outbound');
        }

        if (!$sandbox) {
            // Close the SMTP connection
            $mailer->getTransport()->stop();
        }
    }

    /*
     * Retry a broadcast with failed messages
     *
     * @param Broadcast $broadcast the thing to retry
     * @param array $sms list of failed SMS messages
     * @param array $emails list of failed emails
     * @param integer $retryPersonId person who is attempting a retry
     */

    public static function retryBroadcast($broadcast, $sms, $emails, $retryPersonId)
    {
        if (!$sms->isEmpty()) {
            $phoneNumbers = $sms->pluck('address')->toArray();
            try {
                // Try to annoy people again.
                $status = SMSService::broadcast($phoneNumbers, $broadcast->sms_message);
                foreach ($sms as $message) {
                    $message->update(['status' => Broadcast::STATUS_SENT]);
                }
            } catch (SMSException $e) {
                ErrorLog::recordException($e, 'sms-exception', [
                    'type'          => 'broadcast-retry',
                    'broadcast_id'  => $broadcast->id,
                    'phone_numbers' => $phoneNumbers
                ]);
            }
        }

        // Retry failed emails
        if (!$emails->isEmpty()) {
            $alert = Alert::find($broadcast->alert_id);
            $body = (new RBSMail($broadcast->subject, $broadcast->email_message, $alert))->render();

            list($mailer, $emailMessage) = RBS::setupSMTP($broadcast->sender_address, $broadcast->subject, $body);

            foreach ($emails as $message) {
                $person = $message->person;
                $to = [ $message->address => $person->callsign.' ('.$person->first_name.' '.$person->last_name.')' ];
                $emailMessage->setTo($to);
                try {
                    if ($mailer->send($emailMessage)) {
                        $message->update([ 'status' =>  Broadcast::STATUS_SENT ]);
                    }
                } catch (\Swift_TransportException $e) {
                    ErrorLog::recordException($e, 'email-exception', [
                        'type'                 => 'broadcast-retry',
                        'broadcast_id'         => $broadcast->id,
                        'broadcast_message_id' => $message->id,
                        'email'                => $message->address
                     ]);
                }
            }

            // Close the SMTP connection.
            $mailer->getTransport()->stop();
        }

        // Update the fail counters
        $broadcast->sms_failed = BroadcastMessage::countFail($broadcast->id, 'sms');
        $broadcast->email_failed = BroadcastMessage::countFail($broadcast->id, 'email');
        $broadcast->retry_at = SqlHelper::now();
        $broadcast->retry_person_id = $retryPersonId;
        $broadcast->save();
    }

    /*
     * Setup a Swift Mailer mailer & message object.
     *
     * @param string $from sender email address
     * @param string $subject email subject
     * @param string $message email body
     * @return array mailer & email message objects
     */

    public static function setupSMTP($from, $subject, $message)
    {
        $mailerType = config('mail.driver');
        $smtpServer = config('mail.host');

        if (empty($from)) {
            $from = 'do-not-reply@burningman.org';
        }

        if ($mailerType == 'smtp') {
            // talk with a SMTP server
            $smtpUsername = config('mail.username');
            $smtpPassword = config('mail.password');
            $smtpPort = config('mail.port');
            $smtpProtocol = config('mail.encryption');

            $transport = new \Swift_SmtpTransport($smtpServer, $smtpPort, $smtpProtocol);
            $transport->setUsername($smtpUsername);
            $transport->setPassword($smtpPassword);
        } else {
            // Chatting with the localhost server
            if (empty($smtpServer)) {
                $smtpServer = 'localhost';
            }
            $transport = new \Swift_SmtpTransport($smtpServer, 25);
        }

        $mailer = new \Swift_Mailer($transport);

        /*
         * Limit the transport X messages per connection
         * https://us-west-2.console.aws.amazon.com/ses/home
         */

        $limit = config('mail.messages_per_connection') ?? 50;
        $mailer->registerPlugin(new \Swift_Plugins_AntiFloodPlugin($limit));

        $emailMessage = new \Swift_Message();
        $emailMessage->setFrom($from);
        $emailMessage->setSubject($subject);
        // Always send out HTML emails, because people like them fancy styled emails
        $emailMessage->setBody($message, 'text/html');

        return [ $mailer,  $emailMessage ];
    }

    /*
     * Send a Clubhouse message to people
     *
     * @param Alert $alert the alert row
     * @param int $broadcastId the broadcast identifier
     * @param int $senderId person who sending
     * @param array $people list to send to
     * @param string $from sender's callsign
     * @param string $subject message subject
     * @param string $message message body
     */

    public static function broadcastClubhouse($alert, $broadcastId, $senderId, $people, $from, $subject, $message)
    {
        $clubhouseSandbox = setting('BroadcastClubhouseSandbox');

        foreach ($people as $person) {
            if (!$clubhouseSandbox) {
                $pm = new PersonMessage;
                $pm->forceFill([
                    'person_id'         => $person->id,
                    'message_from'      => $from,
                    'creator_person_id' => $senderId,
                    'subject'           => $subject,
                    'body'              => $message
                ]);
                $pm->saveWithoutValidation();
            }

            // Log the message
            BroadcastMessage::record($broadcastId, Broadcast::STATUS_SENT, $person->id, 'clubhouse', $person->callsign, 'outbound');
        }
    }

    /*
     * Build a SQL JOIN to determine who might be on site based
     * on sign ups happening during the event.
     */

    public static function addOnSiteCond($sql)
    {
        list($eventStart, $eventEnd) = RBS::eventDates();
        $eventStart = SqlHelper::quote(date('Y-m-d', $eventStart));
        $eventEnd = SqlHelper::quote(date('Y-m-d', $eventEnd));

        $sql->where(function ($q) use ($eventStart, $eventEnd) {
            $q->whereRaw("EXISTS (SELECT 1 FROM slot INNER JOIN person_slot ON person_slot.slot_id=slot.id WHERE (slot.begins >= $eventStart AND slot.ends <= $eventEnd) AND person_slot.person_id=person.id LIMIT 1)");
            $q->orWhere('person.on_site', true);
        });
    }

    /*
     * Retrieve, or count, people who might be candidates to broadcast to.
     *
     * @param string $broadcastType - a Broadcast::TYPE_* value
     * @param array $params - criteria to search on
     * @param boolean $countOnly - if true return the count not a candidate list
     */

    public static function retrieveRecipients($broadcastType, $params, $countOnly = false)
    {
        $attrs = self::ATTRIBUTES[$broadcastType];

        $year = current_year();

        $alertId = $attrs['alert_id'] ?? $params['alert_id'];
        $alert = Alert::findOrFail($alertId);

        $onSite = $params['on_site'] ?? false;
        $positionSignedup = $params['position_signed_up'] ?? 'any';
        $attending = $params['attending'] ?? false;
        $training  = $params['training'] ?? '';

        /*
         * Here be SQL dragons.
         */

        switch ($broadcastType) {
        /* Allcom, Allcom -> Khaki: EMERGENCY. ALL HANDS ON DECK.
         *            *insert klaxon bell here*
         * alert preference is IGNORED - emergency broadcast cannot be opted out of.
         */
        case Broadcast::TYPE_EMERGENCY:
            $sql = DB::table('person');
            self::addOnSiteCond($sql);
            self::addNotOnDutyJoin($sql);
            break;

        // Broadcast to people on shift
        case Broadcast::TYPE_ONSHIFT:
            $sql = DB::table('timesheet')
                    ->join('person', 'person.id', '=', 'timesheet.person_id')
                    ->whereYear('timesheet.on_duty', current_year())
                    ->whereNull('timesheet.off_duty')
                    ->whereRaw('IFNULL(alert_person.use_sms, TRUE) IS TRUE')
                    ->where('person.sms_on_playa_verified', true)
                    ->where('person.sms_on_playa_stopped', false);
            break;

        // Recruit people for a unstaffed Dirt shift. Basically anyone
        // who might be on playa and who is not on shift.
        case Broadcast::TYPE_RECRUIT_DIRT:
            $sql = DB::table('person');
            self::addOnSiteCond($sql);
            self::addNotOnDutyJoin($sql);
            break;

        // Allcom, Allcom -> Clubhouse, Non Emergency - usually off playa.
        case Broadcast::TYPE_GENERAL:
            $sql = DB::table('person');
            if ($onSite) {
                self::addOnSiteCond($sql);
            }
            break;

        // For Edit Slots and alerting people to shift time change or cancellation.
        // TODO: move this into SlotController.
        case Broadcast::TYPE_SLOT_EDIT:
            $personIds = $params['person_ids'] ?? null;
            if (empty($personIds)) {
                throw new \InvalidArgumentException("Person ids cannot be missing or empty");
            }
            $sql = DB::table('person')->whereIn('id', $personIds);
            self::addAlertPrefJoin($sql);
            break;

        // Broadcast to people who hold a specific position
        case Broadcast::TYPE_POSITION:
        case Broadcast::TYPE_RECRUIT_POSITION:
            $isRecruitPosition = ($broadcastType == Broadcast::TYPE_RECRUIT_POSITION);

            $positionId  = $params['position_id'];

            $sql = DB::table('person_position')
                ->join('person', function ($j) {
                    $j->on('person.id', '=', 'person_position.person_id');
                    $j->where('person.status', 'active');
                })
                ->where('person_position.position_id', $positionId);

            if ($onSite || $isRecruitPosition) {
                self::addOnSiteCond($sql);
            }

            if ($isRecruitPosition) {
                // A shift muster shouldn't target people who are on duty.
                self::addNotOnDutyJoin($sql);
            } elseif ($positionSignedup != 'any') {
                // Is the person signed up for shift this year?
                $cond = "EXISTS (SELECT 1 FROM slot INNER JOIN person_slot ON person_slot.slot_id=slot.id WHERE YEAR(begins)=$year AND slot.position_id=$positionId AND person_slot.person_id=person.id LIMIT 1)";
                if ($positionSignedup == 'not-signed-up') {
                    $cond = "NOT $cond";
                }

                $sql->whereRaw($cond);
            }
            break;

        // Send to all shift sign ups
        case Broadcast::TYPE_SLOT:
            $slotId = $params['slot_id'];
            $sql = DB::table('person_slot')
                ->join('person', 'person.id', '=', 'person_slot.person_id')
                ->where('person_slot.slot_id', $slotId);
            break;

        default:
            throw new \InvalidArgumentException("Unknown type [$broadcastTyp]");
        }

        $isSimple = $attrs['is_simple'] ?? false;

        if ($isSimple) {
            // A simple broadcast will to send a SMS and maybe an email
            $sendSms = true;
            $sendEmail = !isset($attrs['sms_only']);
            $sendClubhouse = false;
        } else {
            $sendSms = $params['send_sms'] ?? false;
            $sendEmail = $params['send_email'] ?? false;
            $sendClubhouse = $params['send_clubhouse'] ?? false;
        }

        // Need to select everyone if a Clubhouse message is to be created.
        if (!$sendClubhouse && ($broadcastType != Broadcast::TYPE_EMERGENCY)) {
            $prefCond = [];
            if ($sendSms) {
                // Figure out which number to send to
                $smsColumn = $alert->on_playa ? 'sms_on_playa' : 'sms_off_playa';
                $prefCond[] = "IFNULL(alert_person.use_sms, TRUE) IS TRUE AND $smsColumn != '' AND {$smsColumn}_verified IS TRUE";
            }
            if ($sendEmail) {
                $prefCond[] = 'IFNULL(alert_person.use_email, TRUE) IS TRUE';
            }
            $prefCond = '('.implode(' OR ', $prefCond).')';
            $sql->whereRaw($prefCond);
        }

        if ($broadcastType != Broadcast::TYPE_SLOT_EDIT) {
            if (isset($attrs['has_status']) && !empty($params['statuses'])) {
                $sql->whereIn('person.status', $params['statuses']);
            } else {
                $sql->where('person.status', 'active');
            }
        }

        $sql->where('person.user_authorized', true);

        self::addAlertPrefJoin($sql, $alert->id);

        if ($attending) {
            $sql->where('person.active_next_event', true);
        }

        if ($training == 'passed'
            || $training == 'registered'
            || $training == 'no-training'
        ) {
            if ($training == 'no-training') {
                $trainingCond = 'NOT EXISTS';
            } else {
                $trainingCond = 'EXISTS';
            }
            $trainingCond .= " (SELECT 1 FROM trainee_status
                INNER JOIN slot on slot.id = trainee_status.slot_id
                WHERE trainee_status.person_id = person.id
                     AND slot.position_id = ".Position::DIRT_TRAINING."
                     AND YEAR(slot.begins) = $year";
            if ($training == 'passed') {
                $trainingCond .= " AND passed=1";
            }
            $trainingCond .= " LIMIT 1)";

            $sql->whereRaw($trainingCond);
        }

        // Just counting?
        if ($countOnly) {
            return $sql->distinct('person.id')->count();
        }

        $cols = [
            DB::raw('DISTINCT person.id'),
            'person.callsign',
            'person.status',
            'person.first_name',
            'person.last_name',
            'person.email',
            'person.sms_on_playa',
            'person.sms_off_playa',
            'person.sms_on_playa_stopped',
            'person.sms_off_playa_stopped',
            'person.sms_on_playa_verified',
            'person.sms_off_playa_verified',
            'person.on_site',
            'person.active_next_event',
        ];

        if ($sendSms) {
            $cols[] = DB::raw('IFNULL(alert_person.use_sms, TRUE) as use_sms');
        }

        if ($sendEmail) {
            $cols[] = DB::raw('IFNULL(alert_person.use_email, TRUE) as use_email');
        }

        $rows = $sql->select($cols)->orderBy('person.callsign')->get();

        foreach ($rows as $row) {
            if (!$sendSms) {
                $row->use_sms = false;
            }

             if (!$sendEmail) {
                 $row->use_email = false;
             }
        }

        return $rows;
    }

    /*
     * Calculate event dates
     *
     * The range is Labor Day - 14 days to Labor Day + 5 days.
     */

    public static function eventDates()
    {
        $year = current_year();
        $laborDay = date('Y-m-d', strtotime("September $year first monday"));

        return [strtotime($laborDay." - 14 days"), strtotime($laborDay." + 5 days")];
    }

    /*
     * Is the event happening?
     *
     * @return bool true if the burn is on, false if everyone is in the default world.
     */

    public static function isDuringEvent()
    {
        list($eventStart, $eventEnd) = self::eventDates();

        $today = strtotime(date('Y-m-d'));

        return ($today >= $eventStart && $today <= $eventEnd);
    }

    /*
     * Add Alert Preference join
     */

    public static function addAlertPrefJoin($sql, $alertId)
    {
        $sql->leftJoin('alert_person', function ($j) use ($alertId) {
            $j->on('alert_person.person_id', '=', 'person.id');
            $j->where('alert_person.alert_id', $alertId);
        });
    }

    /*
     * Add a join to make sure the person is not on duty.
     */

    public static function addNotOnDutyJoin($sql)
    {
        $year = current_year();
        $sql->whereRaw("NOT EXISTS (SELECT 1 FROM timesheet WHERE person.id=timesheet.person_id AND YEAR(timesheet.on_duty)=$year AND timesheet.off_duty IS NULL)");
    }

    /*
     * Notify person of a new Clubhouse message
     *
     * @param Person $person user to notify
     * @param integer $senderId person id who is sending
     * @param string $from sender's callsign (may not be $senderId)
     * @param string $subject message subject
     * @param string $message message body
     */

    public static function clubhouseMessageNotify($person, $senderId, $from, $subject, $message)
    {
        if (!setting('BroadcastClubhouseNotify')) {
            // Not doing this right now
            return;
        }

        if (!in_array($person->status, Person::LIVE_STATUSES) || $person->user_authorized == false) {
            // Person is not qualified or account is locked.
            return;
        }

        $personId = $person->id;
        $callsign = $person->callsign;

        // Figure out which Alert Pref to use based on if the event is running or not
        $duringEvent = self::isDuringEvent();
        $alertId = $duringEvent ? Alert::CLUBHOUSE_NOTIFY_ON_PLAYA : Alert::CLUBHOUSE_NOTIFY_PRE_EVENT;
        $alert = AlertPerson::findAlertForPerson($alertId, $person->id);

        $smsColumn = $duringEvent ? 'sms_on_playa' : 'sms_off_playa';
        $phoneNumber = $person->$smsColumn;

        // Can SMS be used?
        if (($alert == null || $alert->use_sms == true)
          && ($phoneNumber != '' && $person->{$smsColumn . '_verified'} == true && $person->{$smsColumn . '_stopped'} == false)
         ) {
            $sendSMS = true;
        } else {
            $sendSMS = false;
        }

        $sendEmail = ($alert == null || $alert->use_email);

        if (!$sendEmail && !$sendSMS) {
            // Bail - neither email nor SMS can be used
            return;
        }

        $senderAddress = '';

        $logIds = [];

        $smsFail = 0;
        if ($sendSMS) {
            $smsMessage = "You have a new Ranger Clubhouse msg from $from. Subject: ";
            $limit = (RBS::SMS_LIMIT - (strlen($smsMessage) + 3));
            $size = strlen($smsMessage);
            $size = $size > $limit ? $limit : $size;
            $smsMessage .= substr($subject, 0, $size).'  '.RBS::SMS_SUFFIX;

            try {
                $status = SMSService::broadcast([ $phoneNumber ], $smsMessage);
            } catch (SMSException $e) {
                ErrorLog::recordException($e, 'sms-exception', [
                    'type'    => 'clubhouse-notify',
                    'phone'   => $phoneNumber
                 ]);
                $status = Broadcast::STATUS_SERVICE_FAIL;
            }

            if ($status != Broadcast::STATUS_SENT) {
                $smsFail = 1;
            }
            $logIds[] = BroadcastMessage::record(null, $status, $personId, 'sms', $phoneNumber, 'outbound', $smsMessage);
        } else {
            $smsMessage = '';
        }

        $emailFail = 0;
        if ($sendEmail) {
            $email = $person->email;
            try {
                Mail::to($email)->send(new ClubhouseNewMessageMail($person, $from, $subject, $message));
                $status = Broadcast::STATUS_SENT;
            } catch (\Exception $e) {
                ErrorLog::recordException($e, 'email-exception', [
                    'type'  => 'clubhouse-notify',
                    'email' => $message->address
                 ]);
                $status = Broadcast::STATUS_SERVICE_FAIL;
                $emailFail = 1;
            }

            $logIds[] = BroadcastMessage::record(null, $status, $personId, 'email', $email, 'outbound', $message);
        } else {
            $subject = $message = '';
        }

        $broadcast = Broadcast::create([
            'sender_id'       => $senderId,
            'alert_id'        => $alertId,
            'sms_message'     => $smsMessage,
            'sender_address'  => 'do-not-reply@burningman.org',
            'email_message'   => $message,
            'subject'         => $subject,
            'recipient_count' => 1,
            'sms_failed'      => $smsFail,
            'email_failed'    => $emailFail,
            'sent_sms'        => $sendSMS,
            'sent_email'      => $sendEmail,
            'sent_clubhouse'  => 0
        ]);

        // Link the BroadcastMessage with the Broadcast
        BroadcastMessage::whereIn('id', $logIds)->update([ 'broadcast_id' => $broadcast->id ]);
    }
}

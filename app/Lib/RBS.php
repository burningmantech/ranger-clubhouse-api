<?php

/*
 * The Ranger Broadcasting Service
 */

namespace App\Lib;

use App\Exceptions\UnacceptableConditionException;
use App\Helpers\SqlHelper;
use App\Mail\ClubhouseNewMessageMail;
use App\Mail\RBSMail;
use App\Models\Alert;
use App\Models\AlertPerson;
use App\Models\Broadcast;
use App\Models\BroadcastMessage;
use App\Models\ErrorLog;
use App\Models\MailLog;
use App\Models\Person;
use App\Models\PersonMessage;
use App\Models\Position;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use ReflectionException;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class RBS
{
    /*
     * Positions which may have frequenty shift muster requests.
     */
    const FREQUENT_MUSTER_POSITIONS = [
        Position::DOUBLE_OH_7,
        Position::DIRT_GREEN_DOT,
        Position::GREEN_DOT_LEAD,
        Position::GREEN_DOT_MENTOR,
        Position::HQ_WINDOW,
        Position::RSC_SHIFT_LEAD,
        Position::TROUBLESHOOTER,
    ];

    /*
     * Attributes describes each broadcast type.
     *
     * The follow keys help define what criteria a broadcast uses/requires:
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
            'has_status' => true,
            'has_restrictions' => true,
        ],

        Broadcast::TYPE_POSITION => [
            'has_status' => true,
            'has_position' => true,
            'alerts' => [Alert::TRAINING, Alert::SHIFT_CHANGE, Alert::SHIFT_MUSTER, Alert::SHIFT_CHANGE_PRE_EVENT, Alert::SHIFT_MUSTER_PRE_EVENT]
        ],

        Broadcast::TYPE_SLOT => [
            'has_slot' => true,
            'alerts' => [Alert::TRAINING, Alert::SHIFT_CHANGE, Alert::SHIFT_CHANGE_PRE_EVENT]
        ],

        /*
         * TYPE_EDIT_SLOT is special case since the form is hit from the Edit Slot page
         * when a user updates the times for a slot or deletes a slot.
         */

        Broadcast::TYPE_SLOT_EDIT => [
            'alerts' => [Alert::TRAINING, Alert::SHIFT_CHANGE, Alert::SHIFT_CHANGE_PRE_EVENT],
        ],

        /*
         * Start of Simple Broadcasts
         *
         * These will only accept a SMS sized message. The email subject is set
         * the message. No Clubhouse message created.
         */

        Broadcast::TYPE_ONSHIFT => [
            'alert_id' => Alert::ON_SHIFT,
            'is_simple' => true,
            'sms_only' => true,
        ],

        Broadcast::TYPE_RECRUIT_DIRT => [
            'alert_id' => Alert::SHIFT_MUSTER,
            'is_simple' => true,
        ],

        Broadcast::TYPE_RECRUIT_POSITION => [
            'alert_id' => Alert::SHIFT_MUSTER,
            'is_simple' => true,
            'has_muster_position' => true
        ],

        Broadcast::TYPE_EMERGENCY => [
            'alert_id' => Alert::EMEREGENCY_BROADCAST,
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
    const SMS_LIMIT = 142; // 160 sms limit - length(SMS_PREFIX + SMS_SUFFIX)


    /*
     * Send an SMS message to list of people
     *
     * @param Alert $alert the alert row
     * @param int $broadcastId the broadcast identifier
     * @param int $senderId person who sending
     * @param array $people list to send to
     * @param string $message SMS text
     * @return array [ success count, fail count ]
     */

    public static function broadcastSMS($alert, $broadcastId, $senderId, $people, $message)
    {
        // Figure out which number to send to
        if ($alert->on_playa) {
            $smsColumn = 'sms_on_playa';
        } else {
            $smsColumn = 'sms_off_playa';
        }

        $stopColumn = $smsColumn . '_stopped';
        $verifiedColumn = $smsColumn . '_verified';

        $phoneNumbers = [];
        $recipients = [];

        $isEmergency = ($alert->id == Alert::EMEREGENCY_BROADCAST);

        $fails = 0;
        $sent = 0;

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
                'phone' => $phone,
            ];
        }

        if (count($phoneNumbers) > 0) {
            /*
             * Fire away! Annoy the people for fun and profit...
             */

            if (!setting('BroadcastSMSSandbox')) {
                try {
                    $status = SMSService::broadcast($phoneNumbers, $message);
                    if ($status == Broadcast::STATUS_SENT) {
                        $sent++;
                    } else {
                        $fails++;
                    }
                } catch (SMSException $e) {
                    // meh - usually the Internet is spotty
                    ErrorLog::recordException($e, 'sms-exception', [
                        'type' => 'broadcast',
                        'broadcast_id' => $broadcastId,
                        'phone_numbers' => $phoneNumbers
                    ]);
                    $status = Broadcast::STATUS_SERVICE_FAIL;
                    $fails++;
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

        Broadcast::where('id', $broadcastId)->update(['sms_failed' => $fails]);

        return [$sent, $fails];
    }

    /**
     * Send an Email message to list of people
     *
     * @param $alert
     * @param int $broadcastId the broadcast identifier
     * @param int $senderId person who sending
     * @param array $people list to send to
     * @param string $subject email subject
     * @param string $message email body
     * @return array [ success count, failed count ]
     * @throws ReflectionException
     */

    public static function broadcastEmail($alert, $broadcastId, $senderId, $people, $subject, $message, $expiresAt): array
    {
        $sandbox = setting('BroadcastMailSandbox');

        if (!$sandbox) {
            // Wrap the message in an HTML email template
            $body = (new RBSMail($subject, $message, $alert, $expiresAt))->render();
            $mailer = self::setupSMTP();
        }

        $force = ($alert->id == Alert::EMEREGENCY_BROADCAST);

        $fails = 0;
        $sent = 0;

        $from = setting('DoNotReplyEmail');

        foreach ($people as $person) {
            // Skip if user has not set email as a delivery mechanism
            if (!$person->use_email && !$force) {
                $person->email_status = 'no-contact';
                continue;
            }

            $email = $person->email;
            $personId = $person->id;

            if ($sandbox) {
                // In sandbox mode.. don't send anything.
                $status = 'sent';
                $sent++;
            } else {
                // Sender format is "Callsign (Real Name)"
                $firstName = empty($person->preferred_name) ? $person->first_name : $person->preferred_name;
                $emailMessage = self::createEmail($from,
                    new Address($email, $person->callsign . ' (' . $firstName . ' ' . $person->last_name . ')'),
                    $subject,
                    $body);
                try {
                    $sentMessage = $mailer->send($emailMessage);
                    $status = Broadcast::STATUS_SENT;
                    $sent++;
                    MailLog::create([
                        'person_id' => $personId,
                        'sender_id' => $senderId,
                        'from_email' => $from,
                        'to_email' => $email,
                        'broadcast_id' => $broadcastId,
                        'message_id' => $sentMessage->getMessageId()
                    ]);
                } catch (TransportExceptionInterface $e) {
                    $fails++;
                    $status = Broadcast::STATUS_SERVICE_FAIL;
                    ErrorLog::recordException($e, 'rbs-email-exception', [
                        'type' => 'broadcast',
                        'broadcast_id' => $broadcastId,
                        'email' => $email,
                        'person_id' => $person->id
                    ]);
                }
            }

            $person->email_status = $status;
            BroadcastMessage::record($broadcastId, $status, $personId, 'email', $email, 'outbound');
        }

        Broadcast::where('id', $broadcastId)->update(['email_failed' => $fails]);

        if (!$sandbox) {
            // Close the SMTP connection
            unset($mailer);
        }

        return [$sent, $fails];
    }

    /**
     * Retry a broadcast with failed messages
     *
     * @param $broadcast
     * @param $sms
     * @param $emails
     * @param int $retryPersonId
     * @throws ReflectionException
     */

    public static function retryBroadcast($broadcast, $sms, $emails, int $retryPersonId): void
    {
        if (!$sms->isEmpty()) {
            $phoneNumbers = $sms->pluck('address')->toArray();
            try {
                // Try to annoy people again.
                SMSService::broadcast($phoneNumbers, $broadcast->sms_message);
                foreach ($sms as $message) {
                    $message->update(['status' => Broadcast::STATUS_SENT]);
                }
            } catch (SMSException $e) {
                ErrorLog::recordException($e, 'sms-exception', [
                    'type' => 'broadcast-retry',
                    'broadcast_id' => $broadcast->id,
                    'phone_numbers' => $phoneNumbers
                ]);
            }
        }

        // Retry failed emails
        if (!$emails->isEmpty()) {
            $alert = Alert::find($broadcast->alert_id);
            $body = (new RBSMail($broadcast->subject, $broadcast->email_message, $alert, $broadcast->expiresAt))->render();

            $mailer = self::setupSMTP();

            foreach ($emails as $message) {
                $person = $message->person;
                $firstName = empty($person->preferred_name) ? $person->first_name : $person->preferred_name;
                $emailMessage = self::createEmail(
                    $broadcast->sender_address,
                    new Address($message->address, $person->callsign . ' (' . $firstName . ' ' . $person->last_name . ')'),
                    $broadcast->subject,
                    $body
                );
                try {
                    $sentMessage = $mailer->send($emailMessage);
                    $message->update(['status' => Broadcast::STATUS_SENT]);
                    MailLog::create([
                        'person_id' => $person->id,
                        'broadcast_id' => $broadcast->id,
                        'from_email' => $broadcast->sender_address,
                        'to_email' => $message->address,
                        'message_id' => $sentMessage->getMessageId()
                    ]);

                } catch (TransportExceptionInterface $e) {
                    ErrorLog::recordException($e, 'email-exception', [
                        'type' => 'broadcast-retry',
                        'broadcast_id' => $broadcast->id,
                        'broadcast_message_id' => $message->id,
                        'email' => $message->address
                    ]);
                }
            }

            // Close the SMTP connection.
            unset($mailer);
        }

        // Update the fail counters
        $broadcast->sms_failed = BroadcastMessage::countFail($broadcast->id, 'sms');
        $broadcast->email_failed = BroadcastMessage::countFail($broadcast->id, 'email');
        $broadcast->retry_at = now();
        $broadcast->retry_person_id = $retryPersonId;
        $broadcast->save();
    }

    /**
     * Set up a Symphony Mailer object
     *
     * @return EsmtpTransport
     */

    public static function setupSMTP(): EsmtpTransport
    {
        $mailer = config('mail.mailers.smtp');
        $smtpServer = $mailer['host'];

        if ($mailer['transport'] !== 'smtp') {
            throw new RuntimeException("Unknown transport type " . $mailer['transport']);
        }

        // talk with a SMTP server
        $smtpUsername = $mailer['username'] ?? '';
        $smtpPassword = $mailer['password'] ?? '';
        $smtpPort = $mailer['port'] ?? 25;

        // Leave encryption option false, connection will be upgraded to TLS during the connection negotiation.
        $transport = new EsmtpTransport($smtpServer, $smtpPort, false);
        if (!empty($smtpUsername)) {
            $transport->setUsername($smtpUsername);
            $transport->setPassword($smtpPassword);
        }

        /*
        * Limit the transport X messages per connection
        * https://us-west-2.console.aws.amazon.com/ses/home
        */

        $limit = config('mail.messages_per_connection') ?? 50;
        $transport->setRestartThreshold($limit);
        return $transport;
    }

    /**
     * Create an email message
     *
     * @param ?string $from
     * @param string|Address $to
     * @param string $subject
     * @param string $message
     * @param string|Carbon|null $expiresAt
     * @return Email
     */

    public static function createEmail(?string $from, string|Address $to, string $subject, string $message): Email
    {
        $emailMessage = new Email();
        if (empty($from)) {
            $from = setting('DoNotReplyEmail');
        }
        $emailMessage->from($from);
        $emailMessage->to($to);
        $emailMessage->subject($subject);
        $emailMessage->html($message);
        return $emailMessage;
    }

    /**
     * Send a Clubhouse message to people
     *
     * @param $alert
     * @param int $broadcastId
     * @param int|null $senderId
     * @param $people
     * @param $from
     * @param $subject
     * @param $message
     * @param Carbon|string|null $expiresAt
     */

    public static function broadcastClubhouse($alert, int $broadcastId, ?int $senderId, $people, $from, $subject, $message, Carbon|string|null $expiresAt): void
    {
        $clubhouseSandbox = setting('BroadcastClubhouseSandbox');

        foreach ($people as $person) {
            if (!$clubhouseSandbox) {
                $pm = new PersonMessage;
                $pm->forceFill([
                    'person_id' => $person->id,
                    'message_from' => $from,
                    'creator_person_id' => $senderId,
                    'subject' => $subject,
                    'body' => $message,
                    'expires_at' => $expiresAt,
                    'broadcast_id' => $broadcastId,
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
        $training = $params['training'] ?? '';

        $isEmergency = $broadcastType == Broadcast::TYPE_EMERGENCY;

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
                    throw new UnacceptableConditionException("Person ids cannot be missing or empty");
                }
                $sql = DB::table('person')->whereIntegerInRaw('id', $personIds);
                self::addAlertPrefJoin($sql, $alertId);
                break;

            // Broadcast to people who hold a specific position
            case Broadcast::TYPE_POSITION:
            case Broadcast::TYPE_RECRUIT_POSITION:
                $isRecruitPosition = ($broadcastType == Broadcast::TYPE_RECRUIT_POSITION);

                $positionIds = $params['position_ids'];

                $sql = DB::table('person_position')
                    ->join('person', function ($j) {
                        $j->on('person.id', '=', 'person_position.person_id');
                        $j->where('person.status', 'active');
                    })
                    ->whereIn('person_position.position_id', $positionIds);

                if ($onSite || $isRecruitPosition) {
                    self::addOnSiteCond($sql);
                }

                if ($isRecruitPosition) {
                    // A shift muster shouldn't target people who are on duty.
                    self::addNotOnDutyJoin($sql);
                } elseif ($positionSignedup != 'any') {
                    // Is the person signed up for shift this year?
                    $sql->{$positionSignedup == 'not-signed-up' ? 'whereNotExists' : 'whereExists'}(function ($q) use ($positionIds, $year) {
                        $q->select(DB::raw(1))
                            ->from('slot')
                            ->join('person_slot', 'person_slot.slot_id', 'slot.id')
                            ->where('begins_year', $year)
                            ->whereIn('slot.position_id', $positionIds)
                            ->whereColumn('person_slot.person_id', 'person.id')
                            ->limit(1);
                    });
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
                throw new UnacceptableConditionException("Unknown type [$broadcastType]");
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

        // Need to select everyone if a Clubhouse message is to be created, or it's an emergency.
        if (!$sendClubhouse && !$isEmergency) {
            $prefCond = [];
            if ($sendSms) {
                // Figure out which number to send to
                $smsColumn = $alert->on_playa ? 'sms_on_playa' : 'sms_off_playa';
                $prefCond[] = "IFNULL(alert_person.use_sms, TRUE) IS TRUE AND $smsColumn != '' AND {$smsColumn}_verified IS TRUE";
            }
            if ($sendEmail) {
                $prefCond[] = 'IFNULL(alert_person.use_email, TRUE) IS TRUE';
            }
            if (!empty($prefConf)) {
                $prefCond = '(' . implode(' OR ', $prefCond) . ')';
                $sql->whereRaw($prefCond);
            }
        }

        if ($broadcastType != Broadcast::TYPE_SLOT_EDIT && $broadcastType != Broadcast::TYPE_SLOT) {
            if (isset($attrs['has_status']) && !empty($params['statuses'])) {
                $sql->whereIn('person.status', $params['statuses']);
            } else {
                $sql->where('person.status', Person::ACTIVE);
            }
        }

        // Safety check: These statuses should never, ever receive a broadcast
        $sql->whereNotIn('status', Person::NO_MESSAGES_STATUSES);

        self::addAlertPrefJoin($sql, $alert->id);

        if ($attending) {
            $sql->join('person_slot as attending_signup', 'attending_signup.person_id', 'person.id');
            $sql->join('slot as attending_slot', function ($j) use ($year) {
                $j->on('attending_slot', 'attending_slot.id', 'attending_signup.slot_id');
                $j->where('attending_slot.begins_year', $year);
            });
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
                     AND slot.position_id = " . Position::TRAINING . "
                     AND YEAR(slot.begins) = $year";
            if ($training == 'passed') {
                $trainingCond .= " AND passed=1";
            }
            $trainingCond .= " LIMIT 1)";

            $sql->whereRaw($trainingCond);
        }

        $cols = [
            DB::raw('DISTINCT person.id'),
            'person.callsign',
            'person.status',
            'person.first_name',
            'person.preferred_name',
            'person.last_name',
            'person.email',
            'person.sms_on_playa',
            'person.sms_off_playa',
            'person.sms_on_playa_stopped',
            'person.sms_off_playa_stopped',
            'person.sms_on_playa_verified',
            'person.sms_off_playa_verified',
            'person.on_site',
        ];

        if ($sendSms) {
            $cols[] = DB::raw('IFNULL(alert_person.use_sms, TRUE) as use_sms');
        }

        if ($sendEmail) {
            $cols[] = DB::raw('IFNULL(alert_person.use_email, TRUE) as use_email');
        }

        $rows = $sql->select($cols)->orderBy('person.callsign')->get();

        $smsColumn = $alert->on_playa ? 'sms_on_playa' : 'sms_off_playa';
        $verifyColumn = $smsColumn . '_verified';
        $stopColumn = $smsColumn . '_stopped';

        foreach ($rows as $row) {
            if (!$sendSms) {
                $row->use_sms = false;
            } else {
                $haveSMS = !empty($row->{$smsColumn}) && $row->{$verifyColumn} && !$row->{$stopColumn};
                if (!$haveSMS) {
                    $row->use_sms = false; // do not have a number that can be used
                } else if ($isEmergency) {
                    $row->use_sms = true; // force override
                }
            }

            if ($isEmergency) {
                $row->use_email = true;
            } else if (!$sendEmail) {
                $row->use_email = false;
            }
        }

        /*
         * A person is only a candidate recipient if one of the following is true:
         * - A Clubhouse message is to be sent
         * - It's an emergency
         * - A SMS is being send and the alert pref is allowed & number is okay (has verified #, and is not stopped).
         * - The email alert is set to allow
         */

        if (!$sendClubhouse && !$isEmergency) {
            $rows = $rows->filter(fn($row) => ($sendSms && $row->use_sms) || ($sendEmail && $row->use_email))
                ->values();
        }

        if ($countOnly) {
            return $rows->count();
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

        return [strtotime($laborDay . " - 14 days"), strtotime($laborDay . " + 5 days")];
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

        if (in_array($person->status, Person::NO_MESSAGES_STATUSES)) {
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
        $smsSandboxed = setting('BroadcastSMSSandbox');
        $emailSandboxed = setting('BroadcastMailSandbox');

        if ($sendSMS) {
            $smsMessage = "You have a new Ranger Clubhouse msg from $from. Subject: ";
            $limit = (RBS::SMS_LIMIT - (strlen($smsMessage) + 3));
            $size = strlen($smsMessage);
            $size = $size > $limit ? $limit : $size;
            $smsMessage .= substr($subject, 0, $size) . '  ' . RBS::SMS_SUFFIX;

            try {
                if (!$smsSandboxed) {
                    $status = SMSService::broadcast([$phoneNumber], $smsMessage);
                } else {
                    $status = Broadcast::STATUS_SENT;
                }
            } catch (SMSException $e) {
                ErrorLog::recordException($e, 'sms-exception', [
                    'type' => 'clubhouse-notify',
                    'phone' => $phoneNumber
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
            if (!$emailSandboxed) {
                if (!mail_send(new ClubhouseNewMessageMail($person, $from, $subject, $message))) {
                    $status = Broadcast::STATUS_SERVICE_FAIL;
                    $emailFail = 1;
                } else {
                    $status = Broadcast::STATUS_SENT;
                }
            } else {
                $status = Broadcast::STATUS_SENT;
            }

            $logIds[] = BroadcastMessage::record(null, $status, $personId, 'email', $email, 'outbound', $message);
        } else {
            $subject = $message = '';
        }

        $broadcast = Broadcast::create([
            'sender_id' => $senderId,
            'alert_id' => $alertId,
            'sms_message' => $smsMessage,
            'sender_address' => setting('DoNotReplyEmail'),
            'email_message' => $message,
            'subject' => $subject,
            'recipient_count' => 1,
            'sms_failed' => $smsFail,
            'email_failed' => $emailFail,
            'sent_sms' => $sendSMS,
            'sent_email' => $sendEmail,
            'sent_clubhouse' => 0
        ]);

        // Link the BroadcastMessage with the Broadcast
        BroadcastMessage::whereIntegerInRaw('id', $logIds)->update(['broadcast_id' => $broadcast->id]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Jobs\RBSTransmitClubhouseMessageJob;
use App\Jobs\RBSTransmitEmailJob;
use App\Lib\RBS;
use App\Models\Alert;
use App\Models\Broadcast;
use App\Models\BroadcastMessage;
use App\Models\Person;
use App\Models\Position;
use App\Models\Slot;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RbsController extends ApiController
{
    /**
     * Broadcast configuration
     *
     * @return JsonResponse
     */

    public function config(): JsonResponse
    {
        return response()->json([
            'config' => [
                // Is the event happening?
                'is_during_event' => RBS::isDuringEvent(),
                // Are emails sandboxed?
                'email_sandbox' => setting('BroadcastMailSandbox'),
                // Alert users about new Clubhouse messages via email/sms?
                'clubhouse_message_notify' => setting('BroadcastClubhouseNotify'),
                // Are Clubhouse messages sandboxed?
                'clubhouse_message_sandbox' => setting('BroadcastClubhouseSandbox'),
                // Are SMS messages sandboxed?
                'sms_sandbox' => setting('BroadcastSMSSandbox'),
                // Allowed SMS message size (max SMS size - sms_prefix+sms_suffix)
                'sms_limit' => RBS::SMS_LIMIT,
                // The string prefixed to all SMS messages
                'sms_prefix' => RBS::SMS_PREFIX,
                // The string appeneded to all SMS messages
                'sms_suffix' => RBS::SMS_SUFFIX
            ]
        ]);
    }

    /**
     * Show unverified & stopped phone numbers
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function unverifiedStopped(): JsonResponse
    {
        $this->authorize('unverifiedStopped', Broadcast::class);

        $sql = DB::table('person')
            ->select(
                'id',
                'status',
                'callsign',
                'sms_on_playa',
                'sms_on_playa_verified',
                'sms_on_playa_stopped',
                'sms_off_playa',
                'sms_off_playa_verified',
                'sms_off_playa_stopped'
            )->whereIn('status', Person::LIVE_STATUSES)
            ->orderBy('callsign');

        $unverified = (clone $sql)->where(function ($q) {
            $q->where('sms_on_playa', '!=', '');
            $q->where('sms_on_playa_verified', false);
        })->orWhere(function ($q) {
            $q->where('sms_off_playa', '!=', '');
            $q->where('sms_off_playa_verified', false);
        })->get();

        $stopped = (clone $sql)->where(function ($q) {
            $q->where('sms_on_playa', '!=', '');
            $q->where('sms_on_playa_verified', true);
            $q->where('sms_on_playa_stopped', true);
        })->orWhere(function ($q) {
            $q->where('sms_off_playa', '!=', '');
            $q->where('sms_off_playa_verified', true);
            $q->where('sms_off_playa_stopped', true);
        })->get();

        return response()->json([
            'unverified' => $unverified,
            'stopped' => $stopped
        ]);
    }

    /**
     * Count how many people a broadcast may reach
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function recipients(): JsonResponse
    {
        $params = request()->validate([
            'type' => 'required|string',
            'count_only' => 'sometimes|boolean'
        ]);

        $type = $params['type'];
        $attrs = $this->verifyType($type);

        $countOnly = $params['count_only'] ?? false;
        $criteria = $this->grabCriteria($type);

        return response()->json(['people' => RBS::retrieveRecipients($type, $criteria, $countOnly)]);
    }

    /**
     * Return the items needed (slots, positions, alerts) needed to setup a broadcast form.
     * @param $type
     * @return mixed
     * @throws AuthorizationException
     */

    private function verifyType($type): mixed
    {

        $attrs = RBS::ATTRIBUTES[$type] ?? null;
        if (!$attrs) {
            throw new InvalidArgumentException("Unknown broadcast type");
        }

        $this->authorize('typeAllowed', [Broadcast::class, $type]);

        return $attrs;
    }

    /**
     * Retrieve unknown phone numbers for a year
     *
     * @param $type
     * @return array
     */

    private static function grabCriteria($type): array
    {
        $attrs = RBS::ATTRIBUTES[$type];

        $validations = [
            'send_clubhouse' => 'sometimes|boolean',
            'send_email' => 'sometimes|boolean',
            'send_sms' => 'sometimes|boolean'
        ];

        if (empty($attrs['alert_id'])) {
            $validations['alert_id'] = 'required|integer|exists:alert,id';
        }

        if (isset($attrs['has_position']) || isset($attrs['has_muster_position'])) {
            $validations['position_id'] = 'required|integer|exists:position,id';
            $validations['position_signed_up'] = 'required|string';
        }

        if (isset($attrs['has_restrictions'])) {
            $validations['on_site'] = 'required|boolean';
            $validations['attending'] = 'required|boolean';
            $validations['training'] = 'required|string';
        }

        if (isset($attrs['has_status'])) {
            $validations['statuses'] = 'required|array';
            $validations['statuses.*'] = 'sometimes|string';
        }

        if (isset($attrs['has_slot'])) {
            $validations['slot_id'] = 'required|integer|exists:slot,id';
        }

        if (empty($validations)) {
            return [];
        }

        return request()->validate($validations);
    }

    /**
     * Retrieve basic statistics  (verified, unverified, stopped, etc.)
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function details(): JsonResponse
    {
        prevent_if_ghd_server('RBS stats');
        $params = request()->validate([
            'type' => 'required|string'
        ]);

        $type = $params['type'];
        $attrs = $this->verifyType($type);

        // Copy over the attributes
        $info = [];
        foreach (['is_simple', 'has_status', 'has_slot', 'has_restrictions', 'has_position', 'has_muster_position'] as $attr) {
            if (isset($attrs[$attr])) {
                $info[$attr] = true;
            }
        }

        // Return positions needing more people
        if (isset($attrs['has_muster_position'])) {
            $info['muster_positions'] = [
                'frequent' => Position::find(RBS::FREQUENT_MUSTER_POSITIONS)
                    ->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->map(function ($row) {
                        return ['id' => $row->id, 'title' => $row->title];
                    }),

                'in_progress' => Position::findAllWithInProgressSlots()
                    ->map(function ($row) {
                        return ['id' => $row->id, 'title' => $row->title];
                    })
            ];
        }

        // Return all positions
        if (isset($attrs['has_position'])) {
            $info['positions'] = Position::all()->map(function ($p) {
                return ['id' => $p->id, 'title' => $p->title, 'type' => $p->type];
            })->values();
        }

        // Return all sign ups
        if (isset($attrs['has_slot'])) {
            $groups = Slot::findWithSignupsForYear(current_year())->groupBy('position.title');

            $positionSlots = [];
            foreach ($groups as $title => $slots) {
                $positionSlots[] = [
                    'title' => $title,
                    'slots' => $slots->map(function ($s) {
                        return [
                            'id' => $s->id,
                            'description' => $s->description,
                            'begins' => (string)$s->begins,
                            'signed_up' => $s->signed_up
                        ];
                    })->values()->toArray()
                ];
            }

            usort($positionSlots, function ($a, $b) {
                return strcmp($a['title'], $b['title']);
            });
            $info['slots'] = $positionSlots;
        }

        $alerts = null;
        // And alert types
        if (isset($attrs['alerts'])) {
            $alerts = Alert::find($attrs['alerts']);
        } elseif (!isset($attrs['alert_id'])) {
            $alerts = Alert::findAll();
        }

        if ($alerts) {
            // Filter out alerts for non-RBS broadcasts
            $alerts = $alerts->filter(function ($a) {
                return $a->id != Alert::CLUBHOUSE_NOTIFY_PRE_EVENT
                    && $a->id != Alert::CLUBHOUSE_NOTIFY_ON_PLAYA
                    && $a->id != Alert::RANGER_CONTACT
                    && $a->id != Alert::MENTOR_CONTACT;
            });
            $info['alerts'] = $alerts->map(function ($a) {
                return ['id' => $a->id, 'title' => $a->title, 'on_playa' => $a->on_playa];
            })->values();
        }

        switch ($type) {
            case Broadcast::TYPE_ONSHIFT:
            case Broadcast::TYPE_EMERGENCY:
            case Broadcast::TYPE_RECRUIT_DIRT:
                // Estimate how many people the broadcast might reach.
                $info['receivers'] = RBS::retrieveRecipients($type, [], true);
                break;
        }

        return response()->json(['details' => $info]);
    }

    /**
     * Transmit a message to the world
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function unknownPhones(): JsonResponse
    {
        $this->authorize('unknownPhones', Broadcast::class);
        prevent_if_ghd_server('Unknown phones listing');

        $params = request()->validate([
            'year' => 'required|integer'
        ]);

        return response()->json(['phones' => BroadcastMessage::findUnknownPhonesForYear($params['year'])]);
    }

    /**
     * Set up a validation array to grab and verify the query parameters needed
     * for the given broadcast type.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function stats(): JsonResponse
    {
        $this->authorize('stats', Broadcast::class);

        foreach (Person::LIVE_STATUSES as $status) {
            $sql = DB::table('person')->where('status', $status);

            $sameVerified = (clone $sql)->whereRaw('sms_on_playa=sms_off_playa')
                ->where('sms_on_playa_verified', true)
                ->count();

            $diffVerified = (clone $sql)->where('sms_on_playa', '!=', '')
                ->where('sms_off_playa', '!=', '')
                ->whereRaw('sms_on_playa != sms_off_playa')
                ->where('sms_off_playa_verified', true)
                ->where('sms_on_playa_verified', true)
                ->count();

            $onPlayaVerified = (clone $sql)->whereRaw('sms_on_playa != sms_off_playa')
                ->where('sms_on_playa', '!=', '')
                ->where('sms_on_playa_verified', true)
                ->count();


            $offPlayaVerified = (clone $sql)->whereRaw('sms_on_playa != sms_off_playa')
                ->where('sms_off_playa', '!=', '')
                ->where('sms_off_playa_verified', true)
                ->count();

            $onPlayaStopped = (clone $sql)->where('sms_on_playa', '!=', '')
                ->where('sms_on_playa_verified', true)
                ->where('sms_on_playa_stopped', true)
                ->count();

            $offPlayaStopped = (clone $sql)->where('sms_off_playa', '!=', '')
                ->where('sms_off_playa_verified', true)
                ->where('sms_off_playa_stopped', true)
                ->count();

            $onPlayaUnverified = (clone $sql)->where('sms_on_playa', '!=', '')
                ->where('sms_on_playa_verified', false)
                ->count();

            $offPlayaUnverified = (clone $sql)->where('sms_on_playa', '!=', '')
                ->where('sms_on_playa_verified', false)
                ->count();


            $stats[] = [
                'status' => $status,
                'same_verified' => $sameVerified,
                'diff_verified' => $diffVerified,
                'on_playa_verified' => $onPlayaVerified,
                'off_playa_verified' => $offPlayaVerified,
                'on_playa_stopped' => $onPlayaStopped,
                'off_playa_stopped' => $offPlayaStopped,
                'on_playa_unverified' => $onPlayaUnverified,
                'off_playa_unverified' => $offPlayaUnverified,
            ];
        }

        return response()->json(['stats' => $stats]);
    }

    /**
     * Attempt to resend a failed broadcast
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function transmit(): JsonResponse
    {
        prevent_if_ghd_server('RBS transmission');

        $params = request()->validate([
            'type' => 'required|string',
            'send_sms' => 'sometimes|boolean',
            'send_email' => 'sometimes|boolean',
            'send_clubhouse' => 'sometimes|boolean',
            'from' => 'sometimes|string|required_if:send_email,1',
            'sms_message' => 'sometimes|string|required_if:send_sms,1',
            'message' => 'sometimes|string|required_if:send_email,1',
            'subject' => 'sometimes|string|required_if:send_email,1|required_if:send_clubhouse,1'
        ]);

        $type = $params['type'];
        $attrs = $this->verifyType($type);

        $criteria = $this->grabCriteria($type);

        $sendSMS = $params['send_sms'] ?? false;
        $sendEmail = $params['send_email'] ?? false;
        $sendClubhouse = $params['send_clubhouse'] ?? false;

        $isSimple = isset($attrs['is_simple']);
        $subject = $params['subject'] ?? '';
        $smsMessage = $params['sms_message'] ?? '';
        $message = $params['message'] ?? '';
        $from = $params['from'] ?? '';

        $alertId = $attrs['alert_id'] ?? ($criteria['alert_id'] ?? null);

        if (empty($alertId)) {
            throw new InvalidArgumentException("Alert id must be supplied.");
        }

        $alert = Alert::findOrFail($alertId);

        // Figure out who to annoy
        $people = RBS::retrieveRecipients($type, $criteria);

        if ($isSimple) {
            $subject = $smsMessage;
            $sendClubhouse = false;
            $sendEmail = !isset($attrs['sms_only']);
            $sendSMS = true;
            $from = 'do-not-reply@burningman.org';
        }

        $userId = $this->user->id;
        $broadcast = Broadcast::create([
            'sender_id' => $userId,
            'alert_id' => $alertId,
            'sms_message' => $smsMessage,
            'email_message' => $message,
            'subject' => $subject,
            'sender_address' => $from,
            'recipient_count' => count($people),
            'sent_sms' => $sendSMS,
            'sent_email' => $sendEmail,
            'sent_clubhouse' => $sendClubhouse,
        ]);
        $broadcastId = $broadcast->id;

        // Send the texts
        $smsSuccesses = 0;
        $smsFails = 0;
        if ($sendSMS) {
            // Add a prefix and suffix to message.
            $smsMessage = RBS::SMS_PREFIX . $smsMessage . RBS::SMS_SUFFIX;
            list ($smsSuccesses, $smsFails) = RBS::broadcastSMS($alert, $broadcastId, $userId, $people, $smsMessage);
        }

        // Send the Clubhouse messages
        $clubhouseMessages = 0;
        if ($sendClubhouse) {
            RBSTransmitClubhouseMessageJob::dispatch($alert, $broadcastId, $userId, $people, 'The Ranger Broadcasting System', $subject, $message);
            $clubhouseMessages = $people->count();
        }

        // Send out the emails
        $emailsQueued = 0;
        if ($sendEmail) {
            RBSTransmitEmailJob::dispatch($alert, $broadcastId, $userId, $people, $from, $subject, $message);
            foreach ($people as $person) {
                if ($person->use_email) {
                    $emailsQueued++;
                }
            }
        }

        $recipients = $people->map(function ($person) use ($sendSMS) {
            $p = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'status' => $person->status,
            ];
            if ($sendSMS) {
                $p['sms_status'] = $person->sms_status;
            }
            return $p;
        })->values();

        return response()->json([
            'people' => $recipients,
            'emails_queued' => $emailsQueued,
            'clubhouse_messages' => $clubhouseMessages,
            'sms_successes' => $smsSuccesses,
            'sms_fails' => $smsFails,
            'sent_sms' => $sendSMS,
            'sent_clubhouse' => $sendClubhouse,
            'sent_email' => $sendEmail,
        ]);
    }

    /**
     * Find a person transmit record already built up or built a new one
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function retry(): JsonResponse
    {
        $this->authorize('retry', Broadcast::class);

        $params = request()->validate([
            'broadcast_id' => 'required|integer',
        ]);

        $broadcast = Broadcast::findWithFailedMessages($params['broadcast_id']);

        // Split out SMS & emails
        $sms = $broadcast->messages->filter(function ($r) {
            return ($r->address_type == 'sms');
        });
        $emails = $broadcast->messages->filter(function ($r) {
            return ($r->address_type == 'email');
        });

        // Fire again!
        RBS::retryBroadcast($broadcast, $sms, $emails, $this->user->id);


        $smsSuccess = 0;
        $smsFails = 0;
        $emailSuccess = 0;
        $emailFails = 0;

        $peopleByIds = [];
        foreach ($sms as $message) {
            $person = self::findOrBuildPerson($peopleByIds, $message);
            $person->sms_status = $message->status;
            if ($message->status == Broadcast::STATUS_SENT) {
                $smsSuccess++;
            } else {
                $smsFails++;
            }
        }

        foreach ($emails as $message) {
            $person = self::findOrBuildPerson($peopleByIds, $message);
            $person->email_status = $message->status;
            if ($message->status == Broadcast::STATUS_SENT) {
                $emailSuccess++;
            } else {
                $emailFails++;
            }
        }

        $people = array_values($peopleByIds);
        usort($people, function ($a, $b) {
            return strcasecmp($a->callsign, $b->callsign);
        });

        // Response should match transmit()
        return response()->json([
            'people' => $people,
            'sms_successes' => $smsSuccess,
            'sms_fails' => $smsFails,
            'email_queued' => $emailSuccess,
            'sent_sms' => !$sms->isEmpty(),
            'sent_email' => !$emails->isEmpty(),
        ]);
    }

    /*
     * Verify the broadcast type exists, and if the user is allowed to use it.
     */

    private function findOrBuildPerson(&$peopleByIds, $message)
    {
        $person = $peopleByIds[$message->person_id] ?? null;
        if ($person) {
            return $person;
        }

        $pm = $message->person;

        $person = (object)[
            'id' => $pm->id,
            'callsign' => $pm->callsign,
            'status' => $pm->status,
            'sms_status' => 'none',
            'email_status' => 'none',
        ];
        $peopleByIds[$person->id] = $person;

        return $person;
    }
}

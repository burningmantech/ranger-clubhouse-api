<?php

namespace App\Lib;

use App\Mail\TrainerReminderMail;
use App\Models\ErrorLog;
use App\Models\Person;
use App\Models\TraineeNote;
use App\Models\TraineeStatus;
use App\Models\TrainingSession;
use Illuminate\Support\Facades\DB;

class TrainerReminder
{
    const int GRACE_PERIOD_IN_SECONDS = (24 * 3600);

    public static function execute(): void
    {
        $now = now();
        $slots = TrainingSession::select('slot.*')
            ->join('position', 'position.id', '=', 'slot.position_id')
            ->whereLike('position.title', '%Training%')
            ->where('slot.active', true)
            ->where('slot.begins_year', $now->year)
            ->where('slot.did_notify', false)
            ->where('ends_time', '<=', $now->timestamp - self::GRACE_PERIOD_IN_SECONDS)
            ->get();

        foreach ($slots as $slot) {
            $needReminder = self::doesSlotNeedReminder($slot);
            if ($needReminder) {
                self::emailTrainers($slot);
            }
            $slot->did_notify = true;
            if ($needReminder) {
                $slot->auditReason = 'automatic trainer reminder sent';
            } else {
                $slot->auditReason = 'trainer reminder not needed';
            }
            $slot->saveWithoutValidation();
        }
    }

    public static function doesSlotNeedReminder(TrainingSession $slot): bool
    {
        $personIds = DB::table('person_slot')
            ->join('person', 'person.id', '=', 'person_slot.person_id')
            ->whereColumn('person_slot.person_id', '=', 'person.id')
            ->where('person_slot.slot_id', '=', $slot->id)
            ->whereIn('person.status', Person::TRAINING_STATUSES)
            ->pluck('person.id');

        if ($personIds->isEmpty()) {
            // Unlikely to happen
            return true;
        }

        $traineeStatuses = TraineeStatus::whereIn('person_id', $personIds)
            ->where('slot_id', $slot->id)
            ->get()
            ->keyBy('person_id');
        $traineeNotes = TraineeNote::whereIn('person_id', $personIds)
            ->where('slot_id', $slot->id)
            ->get()
            ->groupBy('person_id');

        // TODO: remove this logic when trainee_status.passed is replaced with trainee_status.status
        // For now, stand on our heads to figure out if the session was scored or not.
        $count = $traineeStatuses->count();

        if ($count > 0) {
            $scored = 0;
            foreach ($personIds as $personId) {
                if ($traineeStatuses->get($personId)?->passed) {
                    // Person passed
                    $scored++;
                } else {
                    $notes = $traineeNotes->get($personId);
                    if (!$notes) {
                        // No pass and no notes.
                        continue;
                    }

                    foreach ($notes as $note) {
                        // Only care about notes left after the session starts.
                        if ($note->created_at->timestamp >= $slot->begins_time) {
                            $scored++;
                            continue 2;
                        }
                    }
                }
            }

            if (($scored / (float)$personIds->count()) >= 0.75) {
                // 75% or more was passed or had notes, assume session has been scored.
                return false;
            }
        }

        return true;
    }

    public static function emailTrainers(TrainingSession $slot): void
    {
        // Grab the trainers
        $trainerGroups = $slot->retrieveTrainers();
        $emails = [];
        foreach ($trainerGroups as $group) {
            foreach ($group['trainers'] as $trainer) {
                $emails[] = $trainer['email'];
            }
        }

        if (empty($slot->position->contact_email)) {
            ErrorLog::record('position-no-contact-email', [
                'message' => 'No contact email is set on position for training results reminder emails',
                'slot' => $slot,
                'position' => $slot->position,
            ]);
        } else {
            mail_send(new TrainerReminderMail($slot, $emails));
        }
    }
}
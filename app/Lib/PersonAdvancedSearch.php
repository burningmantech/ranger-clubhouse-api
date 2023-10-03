<?php

namespace App\Lib;

use App\Models\AccessDocument;
use App\Models\Person;
use App\Models\PersonPhoto;
use App\Models\Position;
use App\Models\Timesheet;
use App\Models\TrainerStatus;
use App\Models\Training;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PersonAdvancedSearch
{
    const NO_RESULTS = [
        'people' => [],
    ];

    public static function execute($query): array
    {
        $statuses = $query['statuses'] ?? null;
        if (!empty($statuses)) {
            $statuses = explode(',', $statuses);
        }
        $yearCreated = $query['year_created'] ?? null;
        $statusYear = $query['status_year'] ?? null;

        $yearsWorked = $query['years_worked'] ?? null;
        $yearsWorkedOp = $query['years_worked_op'] ?? null;
        $includeYearsWorked = $query['include_years_worked'] ?? false;

        $includePhotoStatus = $query['include_photo_status'] ?? false;
        $photoStatus = $query['photo_status'] ?? null;

        $includeOnlineCourse = $query['include_online_course'] ?? false;
        $onlineCourseStatus = $query['online_course_status'] ?? null;

        $includeTrainingStatus = $query['include_training_status'] ?? false;
        $trainingStatus = $query['training_status'] ?? null;

        $includeTicketingInfo = $query['include_ticketing_info'] ?? false;
        $ticketingStatus = $query['ticketing_status'] ?? null;

        $peopleByStatusChange = [];

        $sql = Person::select('person.*');
        $year = current_year();

        if ($photoStatus) {
            $includePhotoStatus = true;
        }

        if ($trainingStatus) {
            $includeTrainingStatus = true;
        }

        if ($statusYear) {
            if (empty($statuses)) {
                throw new InvalidArgumentException("status_year set yet not statuses given");
            }

            $personStatus = DB::table('person_status')
                ->whereIn('new_status', $statuses)
                ->whereYear('created_at', $statusYear)
                ->get();

            if ($personStatus->isEmpty()) {
                return self::NO_RESULTS;
            }
            $sql->whereIntegerInRaw('person.id', $personStatus->pluck('person_id'));
            $peopleByStatusChange = $personStatus->keyBy('person_id');
        } else if ($statuses) {
            $sql->whereIn('person.status', $statuses);
        }

        if ($onlineCourseStatus || $includeOnlineCourse) {
            $includeOnlineCourse = true;
            $sql->leftJoin('person_event',
                function ($j) use ($year) {
                    $j->on('person_event.person_id', 'person.id');
                    $j->where('person_event.year', $year);
                }
            );
            $sql->leftJoin('person_online_training',
                function ($j) use ($year) {
                    $j->on('person_online_training.person_id', 'person.id');
                    $j->whereYear('person_online_training.completed_at', $year);
                }
            );
            $sql->addSelect(
                'person_event.lms_enrolled_at as online_course_started',
                'person_online_training.completed_at as online_course_finished',
            );
            if ($onlineCourseStatus == 'missing') {
                // Person may have been directly enrolled in Moodle.. i.e., no enrollment date but has a finish date.
                $sql->whereNull('person_event.lms_enrolled_at');
                $sql->whereNull('person_online_training.completed_at');
            } else if ($onlineCourseStatus == 'started') {
                $sql->whereNotNull('person_event.lms_enrolled_at');
            } else if ($onlineCourseStatus == 'completed') {
                $sql->whereNotNull('person_online_training.completed_at');
            }
        }

        if ($yearCreated) {
            if ($yearCreated == 1) {
                // Special-case, some accounts created prior to 2008 do not have a create date.
                $sql->whereNull('created_at');
            } else {
                $sql->whereYear('created_at', $yearCreated);
            }
        }

        if ($includePhotoStatus) {
            if ($photoStatus == PersonPhoto::MISSING) {
                $sql->whereNull('person.person_photo_id');
            } else if ($photoStatus) {
                $sql->join('person_photo', function ($j) use ($photoStatus) {
                    $j->on('person.person_photo_id', 'person_photo.id');
                    $j->where('person_photo.status', $photoStatus);
                });
            } else {
                $sql->with('person_photo:id,status');
            }
        }

        if ($includeTicketingInfo || $ticketingStatus) {
            if (!$onlineCourseStatus && !$includeOnlineCourse) {
                $sql->leftJoin('person_event', function ($j) use ($year) {
                    $j->on('person_event.person_id', 'person.id');
                    $j->where('person_event.year', $year);
                });
            }

            $sql->addSelect(
                'person_event.ticketing_started_at',
                'person_event.ticketing_finished_at',
            );

            if ($ticketingStatus) {
                switch ($ticketingStatus) {
                    case 'started':
                        $sql->whereNotNull('person_event.ticketing_started_at');
                        break;

                    case 'not-started':
                        $sql->whereNull('person_event.ticketing_started_at');
                        break;

                    case 'finished':
                        $sql->whereNotNull('person_event.ticketing_finished_at');
                        break;

                    case 'not-finished-claimed':
                        $sql->whereExists(function ($q) {
                            $q->from('access_document as claimed')
                                ->select(DB::raw(1))
                                ->whereColumn('claimed.person_id', 'person.id')
                                ->whereIn('claimed.type', [AccessDocument::STAFF_CREDENTIAL, AccessDocument::SPT])
                                ->where('claimed.status', AccessDocument::CLAIMED)
                                ->limit(1);
                        });
                        break;
                }

                if ($ticketingStatus == 'not-finished' || $ticketingStatus == 'not-finished-claimed' || $ticketingStatus == 'not-finished-banked') {
                    $sql->whereNotNull('person_event.ticketing_started_at');
                    $sql->whereNull('person_event.ticketing_finished_at');
                }
            }
        }

        $rows = $sql->orderBy('callsign')->get();
        $yearsById = [];
        $trainersById = [];
        $traineesById = [];

        if ($rows->isNotEmpty()) {
            if ($includeYearsWorked || $yearsWorked !== null) {
                $yearsById = Timesheet::yearsRangeredCountForIds($rows->pluck('id')->toArray());
            }

            if ($includeTrainingStatus) {
                $trainersById = DB::table('slot')
                    ->select('person_slot.person_id', 'person_slot.created_at', 'begins', 'trainer_status.status')
                    ->join('person_slot', 'person_slot.slot_id', 'slot.id')
                    ->leftJoin('trainer_status', function ($j) {
                        $j->on('trainer_status.trainer_slot_id', 'slot.id');
                        $j->whereColumn('trainer_status.person_id', 'person_slot.person_id');
                    })
                    ->whereIn('position_id', [Position::TRAINER, Position::TRAINER_ASSOCIATE, Position::TRAINER_UBER])
                    ->where('begins_year', $year)
                    ->orderBy('person_slot.person_id')
                    ->orderBy('begins')
                    ->get()
                    ->groupBy('person_id');

                $traineesById = DB::table('slot')
                    ->select('person_slot.person_id', 'person_slot.created_at', 'trainee_status.passed', 'slot.begins', 'slot.timezone')
                    ->join('person_slot', 'person_slot.slot_id', 'slot.id')
                    ->leftJoin('trainee_status', function ($j) {
                        $j->on('trainee_status.slot_id', 'slot.id');
                        $j->whereColumn('trainee_status.person_id', 'person_slot.person_id');
                    })
                    ->where('position_id', Position::TRAINING)
                    ->where('begins_year', $year)
                    ->orderBy('person_slot.person_id')
                    ->orderBy('begins')
                    ->get()
                    ->groupBy('person_id');
            }
        }

        if ($yearsWorked !== null) {
            $filteredRows = [];
            foreach ($rows as $person) {
                $years = $yearsById[$person->id] ?? 0;
                if (
                    ($yearsWorkedOp == 'eq' && $years == $yearsWorked)
                    || ($yearsWorkedOp == 'gte' && $years >= $yearsWorked)
                    || ($yearsWorkedOp == 'lte' && $years <= $yearsWorked)) {
                    $filteredRows[] = $person;
                    $yearsById[$person->id] = $years;
                }
            }
            $rows = $filteredRows;
        }

        $trainingStatusById = [];

        if ($includeTrainingStatus) {
            $filteredRows = [];
            $now = now();
            foreach ($rows as $person) {
                $trainer = $trainersById->get($person->id);
                $trainee = $traineesById->get($person->id);

                $status = 'missing';
                $date = null;
                $signup = null;
                if ($trainer) {
                    foreach ($trainer as $slot) {
                        $date = $slot->begins;
                        $signup = $slot->created_at;
                        if ($slot->status == TrainerStatus::ATTENDED) {
                            // stop at first success
                            $status = 'passed';
                            break;
                        } else if ($slot->status === TrainerStatus::NO_SHOW) {
                            $status = 'failed';
                        } else {
                            $status = 'pending';
                        }
                    }
                } else if ($trainee) {
                    foreach ($trainee as $slot) {
                        $date = $slot->begins;
                        $signup = $slot->created_at;
                        if ($slot->passed) {
                            // stop at first success
                            $status = 'passed';
                            break;
                        } else if (Training::isTimeWithinGracePeriod($slot->begins, $now, $slot->timezone)) {
                            $status = 'pending';
                        } else {
                            $status = 'failed';
                        }
                    }
                }

                $trainingStatusById[$person->id] = [$date, $status, $signup];
                if (!$trainingStatus) {
                    continue;
                }

                if (($trainingStatus == 'signed-up' && $date)
                    || ($trainingStatus == 'missing' && !$date)
                    || ($trainingStatus == 'passed' && $status == 'passed')
                    || ($trainingStatus == 'failed' && $status == 'failed')) {
                    $filteredRows[] = $person;
                }
            }

            if ($trainingStatus) {
                $rows = $filteredRows;
            }
        }

        $people = [];
        foreach ($rows as $person) {
            $result = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'status' => $person->status,
                'email' => $person->email,
                'bpguid' => $person->bpguid,
                'created_at' => $person->created_at ? (string)$person->created_at : null,
                'status_date' => $person->status_date ? (string)$person->status_date : null,
            ];

            if ($statusYear) {
                $statusChange = $peopleByStatusChange[$person->id];
                $result['old_status'] = $statusChange->old_status;
                $result['new_status'] = $statusChange->new_status;
                $result['status_changed_at'] = (string)$statusChange->created_at;
            }
            if ($yearsWorked !== null || $includeYearsWorked) {
                $result['years_worked'] = $yearsById[$person->id] ?? 0;
            }

            if ($includePhotoStatus) {
                $result['photo_status'] = $photoStatus ?? ($person->person_photo->status ?? PersonPhoto::MISSING);
            }

            if ($includeOnlineCourse) {
                $result['online_course_started'] = $person->online_course_started ? (string)$person->online_course_started : null;
                $result['online_course_finished'] = $person->online_course_finished ? (string)$person->online_course_finished : null;
            }

            if ($includeTrainingStatus) {
                $training = $trainingStatusById[$person->id] ?? null;
                if ($training) {
                    $result['training_date'] = $training[0];
                    $result['training_status'] = $training[1];
                    $result['training_signed_up_at'] = $training[2];
                } else {
                    $result['training_status'] = 'missing';
                }
            }

            if ($includeTicketingInfo || $ticketingStatus) {
                $result['ticketing_started_at'] = $person->ticketing_started_at;
                $result['ticketing_finished_at'] = $person->ticketing_finished_at;
            }
            $people[] = $result;
        }

        return [
            'people' => $people,
        ];
    }
}
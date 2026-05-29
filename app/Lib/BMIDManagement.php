<?php

namespace App\Lib;

use App\Models\AccessDocument;
use App\Models\Bmid;
use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\Provision;
use App\Models\Slot;
use App\Models\TraineeStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BMIDManagement
{
    /**
     * Person columns returned by the sanity check.
     *
     * @var array<int, string>
     */
    const array SANITY_PERSON_COLUMNS = [
        'id',
        'callsign',
        'email',
        'first_name',
        'preferred_name',
        'last_name',
        'status',
    ];

    /**
     * Training-related positions, excluded when looking for "real" shifts.
     *
     * @var array<int, int>
     */
    const array TRAINING_POSITIONS = [
        Position::TRAINING,
        Position::TRAINER,
        Position::TRAINER_ASSOCIATE,
        Position::TRAINER_UBER,
    ];

    /**
     * Person statuses that never warrant a WAP/BMID sanity flag.
     *
     * @var array<int, string>
     */
    const array SANITY_EXCLUDED_STATUSES = [
        Person::AUDITOR,
        Person::PROSPECTIVE,
        Person::PAST_PROSPECTIVE,
        Person::ALPHA,
    ];

    /**
     * WAP / Staff Credential statuses considered "live" for sanity checks.
     *
     * @var array<int, string>
     */
    const array LIVE_ACCESS_STATUSES = [
        AccessDocument::CLAIMED,
        AccessDocument::SUBMITTED,
        AccessDocument::QUALIFIED,
    ];

    /**
     * The instant the event is considered to begin for shift comparisons.
     */

    private static function eventStartCutoff(int $year): string
    {
        return "$year-08-15 00:00:00";
    }

    /**
     * The date before which a shift sign-up is considered "early".
     */

    private static function earlyShiftCutoff(int $year): string
    {
        return "$year-08-10";
    }

    /**
     * Sanity check the BMIDs.. really sanity check the WAPs.
     *
     * - Find any person who has non-Training shifts starting before the access date.
     * - Find any person who has an early shift but no WAP or Staff Credential.
     * - Find any person with a Special Price Ticket and a WAP with an access date before the box office opens.
     *
     * @return array<int, array<string, mixed>>
     */

    public static function sanityCheckForYear(int $year): array
    {
        [$shiftsBeforeWap, $shiftsBeforeSubmittedWaps] = self::findShiftsBeforeAccessDate($year);
        $shiftsNoWap = self::findEarlyShiftsWithoutWap($year);

        $boxOfficeOpenDate = setting('TAS_BoxOfficeOpenDate', true);
        $sptBeforeBoxOffice = self::findSptWapsBeforeBoxOffice($year, $boxOfficeOpenDate);

        return [
            [
                'type' => 'shifts-before-access-date',
                'people' => $shiftsBeforeWap,
            ],
            [
                'type' => 'shifts-before-submitted-wap',
                'people' => $shiftsBeforeSubmittedWaps,
            ],
            [
                'type' => 'shifts-no-wap',
                'people' => $shiftsNoWap,
            ],
            [
                'type' => 'spt-before-box-office',
                'box_office' => $boxOfficeOpenDate,
                'people' => $sptBeforeBoxOffice,
            ],
        ];
    }

    /**
     * People whose non-training shifts begin before their WAP/SC access date,
     * or who have a shift but no qualified WAP/SC at all.
     *
     * @return array{0: array<int, Person>, 1: array<int, Person>} [beforeWap, beforeSubmittedWap]
     */

    private static function findShiftsBeforeAccessDate(int $year): array
    {
        $beforeWap = [];
        $beforeSubmittedWap = [];

        $slotIds = DB::table('slot')
            ->where('begins_year', $year)
            ->where('active', true)
            ->where('begins', '>', "$year-08-15")
            ->whereNotIn('position_id', self::TRAINING_POSITIONS)
            ->pluck('id')
            ->all();

        if (empty($slotIds)) {
            return [$beforeWap, $beforeSubmittedWap];
        }

        $personIds = DB::table('person_slot')
            ->whereIntegerInRaw('slot_id', $slotIds)
            ->groupBy('person_id')
            ->pluck('person_id')
            ->all();

        if (empty($personIds)) {
            return [$beforeWap, $beforeSubmittedWap];
        }

        $people = Person::whereIntegerInRaw('id', $personIds)
            ->whereNotIn('status', self::SANITY_EXCLUDED_STATUSES)
            ->orderBy('callsign')
            ->get(self::SANITY_PERSON_COLUMNS);

        if ($people->isEmpty()) {
            return [$beforeWap, $beforeSubmittedWap];
        }

        $peopleIds = $people->pluck('id')->all();

        $accessDocs = AccessDocument::whereIn('type', [AccessDocument::WAP, AccessDocument::STAFF_CREDENTIAL])
            ->whereIn('status', self::LIVE_ACCESS_STATUSES)
            ->whereIntegerInRaw('person_id', $peopleIds)
            ->get()
            ->groupBy('person_id');

        // Bulk load every non-training shift and banked Staff Credential up front
        // (one query each) to avoid a per-person lookup inside the loop.
        $shiftsByPerson = self::nonTrainingShiftsByPerson($year, $peopleIds);

        $bankedByPerson = AccessDocument::where('type', AccessDocument::STAFF_CREDENTIAL)
            ->where('status', AccessDocument::BANKED)
            ->whereIntegerInRaw('person_id', $peopleIds)
            ->get()
            ->groupBy('person_id');

        foreach ($people as $person) {
            $badAccessDocs = [];
            $qualified = self::pickQualifiedAccessDocument($accessDocs->get($person->id), $badAccessDocs);
            $personShifts = $shiftsByPerson->get($person->id);

            // Access at any time always clears the person.
            if ($qualified && $qualified->access_any_time) {
                continue;
            }

            if ($qualified) {
                $slot = $personShifts?->first(fn($s) => $s->begins->lt($qualified->access_date));
                if (!$slot) {
                    continue;
                }

                $person->reason = sprintf(
                    'Shift %s %s is before RAD-%d %s access date %s status %s',
                    $slot->position->title,
                    $slot->begins,
                    $qualified->id,
                    $qualified->getTypeLabel(),
                    $qualified->access_date->toDateString(),
                    $qualified->status
                );

                if ($qualified->status == AccessDocument::SUBMITTED) {
                    $beforeSubmittedWap[] = $person;
                } else {
                    $beforeWap[] = $person;
                }
                continue;
            }

            $person->reason = self::describeMissingWap(
                $personShifts?->first(),
                $bankedByPerson->get($person->id),
                $badAccessDocs
            );
            $beforeWap[] = $person;
        }

        return [$beforeWap, $beforeSubmittedWap];
    }

    /**
     * From a person's live WAP/SC documents, choose the "best" qualifying one:
     * any-time access wins, otherwise the earliest access date. Documents with
     * neither an access date nor any-time access are collected as "bad".
     *
     * @param Collection<int, AccessDocument>|null $docs
     * @param array<int, AccessDocument> $badAccessDocs
     */

    private static function pickQualifiedAccessDocument(?Collection $docs, array &$badAccessDocs): ?AccessDocument
    {
        if (!$docs) {
            return null;
        }

        $qualified = null;
        foreach ($docs as $candidate) {
            if (!$candidate->access_date && !$candidate->access_any_time) {
                // No access date set at all.
                $badAccessDocs[] = $candidate;
                continue;
            }

            if (!$qualified
                || $candidate->access_any_time
                // Guard against a null incumbent access_date (chosen for any-time access).
                || ($qualified->access_date && $candidate->access_date && $candidate->access_date->lt($qualified->access_date))
            ) {
                $qualified = $candidate;
            }
        }

        return $qualified;
    }

    /**
     * Build the reason string for a person who has a shift but no qualified WAP/SC.
     *
     * @param Collection<int, AccessDocument>|null $bankedDocs
     * @param array<int, AccessDocument> $badAccessDocs
     */

    private static function describeMissingWap(?Slot $firstShift, ?Collection $bankedDocs, array $badAccessDocs): string
    {
        $reason = 'No qualified, claimed, submitted WAP or Staff Credential.';

        if ($firstShift) {
            $reason .= " First shift {$firstShift->position->title} {$firstShift->begins}";
        }

        foreach ($bankedDocs ?? [] as $ac) {
            $reason .= " RAD-{$ac->id} Staff Credential banked.";
        }

        foreach ($badAccessDocs as $ac) {
            $reason .= " RAD-{$ac->id} {$ac->getTypeLabel()} status {$ac->status} has no access date.";
        }

        return $reason;
    }

    /**
     * All non-training shifts (on/after the event-start cutoff) for the given
     * people, grouped by person id and ordered earliest-first within each group.
     *
     * @param array<int, int> $personIds
     * @return \Illuminate\Support\Collection<int, Collection<int, Slot>>
     */

    private static function nonTrainingShiftsByPerson(int $year, array $personIds): \Illuminate\Support\Collection
    {
        return Slot::join('person_slot', 'person_slot.slot_id', 'slot.id')
            ->where('begins', '>=', self::eventStartCutoff($year))
            ->whereNotIn('position_id', self::TRAINING_POSITIONS)
            ->whereIntegerInRaw('person_slot.person_id', $personIds)
            ->with('position:id,title')
            ->orderBy('begins')
            ->get(['slot.*', 'person_slot.person_id as shift_person_id'])
            ->groupBy('shift_person_id');
    }

    /**
     * People who signed up for early shifts yet have no live WAP/SC.
     *
     * @return Collection<int, Person>
     */

    private static function findEarlyShiftsWithoutWap(int $year): Collection
    {
        $slotIds = DB::table('slot')
            ->where('begins_year', $year)
            ->where('begins', '>', self::earlyShiftCutoff($year))
            ->pluck('id')
            ->all();

        $personIds = DB::table('person_slot')
            ->select('person_slot.person_id')
            ->whereIntegerInRaw('person_slot.slot_id', $slotIds)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('access_document')
                    ->whereColumn('access_document.person_id', 'person_slot.person_id')
                    ->whereIn('type', [AccessDocument::WAP, AccessDocument::STAFF_CREDENTIAL])
                    ->whereIn('status', AccessDocument::CURRENT_STATUSES);
            })
            ->groupBy('person_slot.person_id')
            ->pluck('person_slot.person_id')
            ->all();

        $people = Person::whereIntegerInRaw('id', $personIds)
            ->whereNotIn('status', self::SANITY_EXCLUDED_STATUSES)
            ->orderBy('callsign')
            ->get(self::SANITY_PERSON_COLUMNS);

        if ($people->isEmpty()) {
            return $people;
        }

        // Bulk load each person's earliest qualifying shift (one query).
        $shiftsByPerson = Slot::join('person_slot', 'person_slot.slot_id', 'slot.id')
            ->whereIntegerInRaw('slot.id', $slotIds)
            ->whereIntegerInRaw('person_slot.person_id', $people->pluck('id')->all())
            ->with('position:id,title')
            ->orderBy('begins')
            ->get(['slot.*', 'person_slot.person_id as shift_person_id'])
            ->groupBy('shift_person_id');

        foreach ($people as $person) {
            $slot = $shiftsByPerson->get($person->id)?->first();
            $person->reason = $slot
                ? "Shift {$slot->position->title} {$slot->begins}"
                : 'Shift (none found)';
        }

        return $people;
    }

    /**
     * People holding a Special Price Ticket whose WAP grants access before the
     * box office opens.
     *
     * @return Collection<int, Person>|array<int, Person>
     */

    private static function findSptWapsBeforeBoxOffice(int $year, $boxOfficeOpenDate): Collection|array
    {
        $waps = AccessDocument::select('access_document.*')
            ->join('access_document as spt', function ($j) {
                $j->on('access_document.person_id', 'spt.person_id')
                    ->where('spt.type', AccessDocument::SPT)
                    ->whereIn('spt.status', self::LIVE_ACCESS_STATUSES);
            })
            ->join('person', function ($j) {
                // NB: intentionally excludes only ALPHA/PROSPECTIVE/AUDITOR (not PAST_PROSPECTIVE).
                $j->on('person.id', 'access_document.person_id')
                    ->whereNotIn('person.status', [Person::ALPHA, Person::PROSPECTIVE, Person::AUDITOR]);
            })
            ->where('access_document.type', AccessDocument::WAP)
            ->whereIn('access_document.status', self::LIVE_ACCESS_STATUSES)
            ->where('access_document.access_date', '<', $boxOfficeOpenDate)
            ->orderBy('access_document.person_id')
            ->get();

        $ids = $waps->pluck('person_id')->unique()->all();
        if (empty($ids)) {
            return [];
        }

        $people = Person::whereIntegerInRaw('id', $ids)
            ->orderBy('callsign')
            ->get(self::SANITY_PERSON_COLUMNS);

        $wapsByPerson = $waps->groupBy('person_id');

        foreach ($people as $person) {
            $wap = $wapsByPerson->get($person->id)?->first();
            if (!$wap) {
                $person->reason = 'NO WAP FOUND?!?';
            } elseif ($wap->access_any_time) {
                $person->reason = "RAD-{$wap->id} {$wap->getTypeLabel()} status {$wap->status} access any time";
            } elseif ($wap->access_date) {
                $person->reason = "RAD-{$wap->id} {$wap->getTypeLabel()} status {$wap->status} access date {$wap->access_date->toDateString()}";
            } else {
                $person->reason = "RAD-{$wap->id} {$wap->getTypeLabel()} status {$wap->status} NO ACCESS DATE";
            }
        }

        return $people;
    }

    /**
     * Retrieve a category of BMIDs to manage.
     *
     * 'alpha': All status Prospective & Alpha
     * 'qualified': Anyone who claimed a ticket or signed up for In-Person Training
     * 'signedup': Current Rangers who are signed up for a shift starting Aug 10th or later
     * 'submitted'/'in_prep'/'ready_to_print': BMIDs in that status
     * 'nonprint': status issues and/or do-not-print BMIDs
     * 'no-shifts': BMIDs for people with no shifts this year
     * default: BMIDs with showers, meals, any access time, or a WAP date prior to the WAP default.
     *
     * @return Collection|array<int, Bmid>
     */

    public static function retrieveCategoryToManage(int $year, string $filter): Collection|array
    {
        $ids = match ($filter) {
            'alpha' => self::alphaPersonIds(),
            'qualified' => self::qualifiedPersonIds($year),
            'signedup' => self::signedUpPersonIds($year),
            'nonprint' => self::bmidPersonIdsWithStatus($year, [Bmid::ISSUES, Bmid::DO_NOT_PRINT]),
            'no-shifts' => self::bmidPersonIdsWithoutShifts($year),
            Bmid::SUBMITTED, Bmid::IN_PREP, Bmid::READY_TO_PRINT => self::bmidPersonIdsWithStatus($year, [$filter]),
            default => self::specialPersonIds($year),
        };

        return Bmid::findForPersonIds($year, $ids, $year == current_year());
    }

    /**
     * All Alpha & Prospective person ids.
     *
     * @return array<int, int>
     */

    private static function alphaPersonIds(): array
    {
        return Person::whereIn('status', [Person::ALPHA, Person::PROSPECTIVE])
            ->pluck('id')
            ->all();
    }

    /**
     * Anyone who claimed a ticket or signed up for In-Person Training.
     *
     * @return array<int, int>
     */

    private static function qualifiedPersonIds(int $year): array
    {
        $ticketIds = DB::table('access_document')
            ->join('person', 'person.id', 'access_document.person_id')
            ->whereIn('person.status', Person::ACTIVE_STATUSES)
            ->whereIn('access_document.type', [AccessDocument::SPT, AccessDocument::STAFF_CREDENTIAL, AccessDocument::WAP])
            ->whereIn('access_document.status', [AccessDocument::CLAIMED, AccessDocument::SUBMITTED])
            ->groupBy('access_document.person_id')
            ->pluck('person_id')
            ->all();

        $slotIds = DB::table('slot')
            ->where('begins_year', $year)
            ->whereIn('position_id', self::TRAINING_POSITIONS)
            ->where('active', true)
            ->pluck('id')
            ->all();

        $signUpIds = empty($slotIds) ? [] : DB::table('person_slot')
            ->join('person', 'person.id', 'person_slot.person_id')
            ->whereIn('person.status', Person::ACTIVE_STATUSES)
            ->whereIntegerInRaw('person_slot.slot_id', $slotIds)
            ->pluck('person_id')
            ->all();

        return array_values(array_unique([...$ticketIds, ...$signUpIds]));
    }

    /**
     * Vets who are signed up for a late shift and/or passed training.
     *
     * @return array<int, int>
     */

    private static function signedUpPersonIds(int $year): array
    {
        $lateSlotIds = Slot::where('begins_year', $year)
            ->where('begins', '>=', self::earlyShiftCutoff($year))
            ->pluck('id');

        $signedUpIds = PersonSlot::whereIntegerInRaw('slot_id', $lateSlotIds)
            ->join('person', function ($j) {
                $j->whereRaw('person.id = person_slot.person_id');
                $j->whereIn('person.status', Person::ACTIVE_STATUSES);
            })
            ->distinct('person_slot.person_id')
            ->pluck('person_id')
            ->all();

        $trainingSlotIds = Slot::join('position', 'position.id', '=', 'slot.position_id')
            ->where('begins_year', $year)
            ->where('position.id', Position::TRAINING)
            ->pluck('slot.id')
            ->all();

        $trainedIds = TraineeStatus::join('person', function ($j) {
            $j->whereRaw('person.id = trainee_status.person_id');
            $j->whereIn('person.status', Person::ACTIVE_STATUSES);
        })
            ->whereIntegerInRaw('slot_id', $trainingSlotIds)
            ->where('passed', 1)
            ->distinct('trainee_status.person_id')
            ->pluck('trainee_status.person_id')
            ->all();

        return array_merge($trainedIds, $signedUpIds);
    }

    /**
     * Person ids of BMIDs in any of the given statuses.
     *
     * @param array<int, string> $statuses
     * @return array<int, int>
     */

    private static function bmidPersonIdsWithStatus(int $year, array $statuses): array
    {
        return Bmid::where('year', $year)
            ->whereIn('status', $statuses)
            ->pluck('person_id')
            ->all();
    }

    /**
     * Person ids of BMIDs for people with no shifts scheduled this year.
     *
     * @return array<int, int>
     */

    private static function bmidPersonIdsWithoutShifts(int $year): array
    {
        return Bmid::where('year', $year)
            ->whereNotExists(function ($q) use ($year) {
                $q->select(DB::raw(1))
                    ->from('person_slot')
                    ->join('slot', 'person_slot.slot_id', 'slot.id')
                    ->whereColumn('bmid.person_id', 'person_slot.person_id')
                    ->whereYear('slot.begins', $year);
            })
            ->pluck('person_id')
            ->all();
    }

    /**
     * "Special" BMIDs: those with titles, meals, showers, qualifying provisions,
     * or an access document granting early/any-time access.
     *
     * @return array<int, int>
     */

    private static function specialPersonIds(int $year): array
    {
        $wapDate = setting('TAS_DefaultWAPDate');

        $specialIds = Bmid::where('year', $year)
            ->where(function ($q) {
                $q->whereNotNull('title1')
                    ->orWhereNotNull('title2')
                    ->orWhereNotNull('title3')
                    ->orWhereNotNull('meals')
                    ->orWhere('showers', true)
                    ->orWhereExists(function ($provision) {
                        $provision->selectRaw(1)
                            ->from('provision')
                            ->whereColumn('provision.person_id', 'bmid.person_id')
                            ->whereIn('provision.status', [Provision::SUBMITTED, Provision::AVAILABLE])
                            ->whereIn('provision.type', [Provision::WET_SPOT, Provision::MEALS]);
                    });
            })
            ->pluck('person_id')
            ->all();

        $adIds = AccessDocument::whereIn('type', [AccessDocument::STAFF_CREDENTIAL, AccessDocument::WAP])
            ->whereIn('status', [
                AccessDocument::BANKED,
                AccessDocument::QUALIFIED,
                AccessDocument::CLAIMED,
                AccessDocument::SUBMITTED,
            ])
            ->where(function ($q) use ($wapDate, $year) {
                // Any AD where the person can get in at any time OR the access date is before WAP access.
                $q->where('access_any_time', 1);
                $q->orWhere(function ($q) use ($wapDate, $year) {
                    $q->whereNotNull('access_date');
                    $q->whereYear('access_date', $year);
                    $q->where('access_date', '<', "$wapDate 00:00:00");
                });
            })
            ->distinct('person_id')
            ->pluck('person_id')
            ->all();

        $provisionIds = Provision::whereIn('type', [Provision::WET_SPOT, Provision::MEALS])
            ->whereIn('status', [Provision::AVAILABLE, Provision::CLAIMED, Provision::SUBMITTED])
            ->distinct('person_id')
            ->pluck('person_id')
            ->all();

        return array_values(array_unique([...$specialIds, ...$adIds, ...$provisionIds]));
    }

    /**
     * Set the BMID titles on BMID records based on what positions a person holds.
     *
     * @return array<int, array<string, mixed>>
     */

    public static function setBMIDTitles(): array
    {
        $year = current_year();

        // positionId => [column, label]; multiple positions may target the same column.
        $titlesByPosition = Bmid::BADGE_TITLES;

        $holders = PersonPosition::whereIn('position_id', array_keys($titlesByPosition))
            ->get(['person_id', 'position_id']);

        $candidateIds = $holders->pluck('person_id')->unique()->values()->all();
        if (empty($candidateIds)) {
            return [];
        }

        $eligibleIds = self::personIdsEligibleForBmidTitles($candidateIds, $year);

        // person_id => [column => label]
        $titlesByPerson = [];
        foreach ($holders as $holder) {
            if (!isset($eligibleIds[$holder->person_id])) {
                // 2024 Council ruling: don't print a BMID unless a ticket/WAP was
                // claimed or the person signed up for In-Person Training.
                continue;
            }

            [$column, $label] = $titlesByPosition[$holder->position_id];
            $titlesByPerson[$holder->person_id][$column] = $label;
        }

        if (empty($titlesByPerson)) {
            return [];
        }

        $bmids = Bmid::findForPersonIds($year, array_keys($titlesByPerson));
        $bmids->load('person:id,callsign,status');
        $bmidsByPerson = $bmids->keyBy('person_id');

        $badges = [];
        foreach ($titlesByPerson as $personId => $titles) {
            $bmid = $bmidsByPerson->get($personId);
            foreach ($titles as $column => $label) {
                $bmid->{$column} = $label;
            }

            $bmid->auditReason = 'maintenance - set BMID titles';
            $bmid->saveWithoutValidation();

            $badges[] = [
                'id' => $bmid->person_id,
                'callsign' => $bmid->person->callsign,
                'status' => $bmid->person->status,
                'title1' => $titles['title1'] ?? null,
                'title2' => $titles['title2'] ?? null,
                'title3' => $titles['title3'] ?? null,
            ];
        }

        usort($badges, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));

        return $badges;
    }

    /**
     * Determine which of the given people are eligible for a printed BMID: they
     * claimed a ticket/WAP or signed up for In-Person Training. Returned as a
     * set (person id => true) for O(1) membership tests.
     *
     * @param array<int, int> $personIds
     * @return array<int, bool>
     */

    private static function personIdsEligibleForBmidTitles(array $personIds, int $year): array
    {
        $claimedIds = AccessDocument::whereIntegerInRaw('person_id', $personIds)
            ->whereIn('type', [...AccessDocument::REGULAR_TICKET_TYPES, AccessDocument::WAP])
            ->whereIn('status', [AccessDocument::CLAIMED, AccessDocument::SUBMITTED])
            ->distinct()
            ->pluck('person_id')
            ->all();

        $trainedIds = DB::table('slot')
            ->join('person_slot', 'person_slot.slot_id', 'slot.id')
            ->where('slot.active', true)
            ->where('slot.begins_year', $year)
            ->where('slot.position_id', Position::TRAINING)
            ->whereIntegerInRaw('person_slot.person_id', $personIds)
            ->distinct()
            ->pluck('person_slot.person_id')
            ->all();

        return array_fill_keys([...$claimedIds, ...$trainedIds], true);
    }
}

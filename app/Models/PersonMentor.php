<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class PersonMentor extends ApiModel
{
    protected $table = 'person_mentor';
    protected bool $auditModel = true;

    protected $fillable = [
        'person_id',
        'mentor_id',
        'mentor_year',
        'status',
        'notes'
    ];

    const string PASS = 'pass';
    const string BONK = 'bonk';
    const string SELF_BONK = 'self-bonk';
    const string PENDING = 'pending';

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public static function haveMentees($personId): bool
    {
        return self::where('mentor_id', $personId)->exists();
    }

    /**
     * Retrieve all mentees for a person, check if the mentees want to be contacted or not.
     *
     * @param int $personId
     * @return array
     */

    public static function retrieveAllForPerson(int $personId): array
    {
        $ids = PersonMentor::where('mentor_id', $personId)
            ->groupBy('person_id')
            ->pluck('person_id')
            ->toArray();

        if (empty($ids)) {
            return [];
        }

        $rows = DB::select(
            'SELECT
            person_mentor.person_id,
            person.callsign,
            person.status,
            mentor.callsign as mentor_callsign,
            person_mentor.mentor_id,
            person_mentor.status as mentor_status,
            person_mentor.mentor_year,
            IFNULL(alert_person.use_email,1) as use_email
            FROM person_mentor
            JOIN person ON person.id=person_mentor.person_id
            LEFT JOIN person_photo ON person.person_photo_id=person_photo.id
            LEFT JOIN person as mentor ON mentor.id=person_mentor.mentor_id
            LEFT JOIN alert_person ON alert_person.person_id=person_mentor.person_id AND alert_person.alert_id=:alert_id
            WHERE person_mentor.person_id IN (' . implode(',', $ids) . ') AND
                EXISTS (SELECT 1 FROM person_mentor pm WHERE pm.mentor_id=:person_id AND pm.person_id=person_mentor.person_id AND pm.mentor_year=person_mentor.mentor_year LIMIT 1)

            ORDER BY person_mentor.mentor_year desc, person.callsign, mentor.callsign',
            [
                'person_id' => $personId,
                'alert_id' => Alert::MENTOR_CONTACT,
            ]
        );

        $lastContact = DB::table(function ($q) use ($personId, $ids) {
            $q->from('contact_log')
                ->select('recipient_person_id', DB::raw('MAX(sent_at) as sent_at'))
                ->where('sender_person_id', $personId)
                ->whereIn('recipient_person_id', $ids)
                ->where('action', 'mentee-contact')
                ->groupBy('recipient_person_id')
                ->orderBy('sent_at', 'desc');
        })->select('recipient_person_id', 'sent_at')
            ->get()
            ->keyBy('recipient_person_id');

        $lastYears = DB::table(function ($q) use ($ids) {
            $q->from('timesheet')
                ->select('person_id', DB::raw('MAX(on_duty) as on_duty'))
                ->whereIn('person_id', $ids)
                ->where('is_echelon', false)
                ->whereNotIn('position_id', [Position::ALPHA, Position::TRAINING])
                ->groupBy('person_id')
                ->orderBy('on_duty', 'desc');
        })->select('person_id', DB::raw('YEAR(on_duty) as year'))
            ->get()
            ->keyBy('person_id');

        $fkas = PersonFka::whereIntegerInRaw('person_id', $ids)
            ->orderBy('person_id')
            ->orderBy('fka_normalized')
            ->get()
            ->groupBy('person_id');

        $photos = PersonPhoto::whereIntegerInRaw('person_id', $ids)
            ->join('person', 'person.person_photo_id', 'person_photo.id')
            ->get()
            ->keyBy('person_id');

        $years = [];
        foreach ($rows as $row) {
            $menteeId = $row->person_id;
            $year = $row->mentor_year;

            if (!isset($years[$year])) {
                $years[$year] = [];
            }

            if (!isset($years[$year][$menteeId])) {
                /*
                 * sanitize the status. A disabled account, or status that is not
                 * active or inactive is marked 'not active'.
                 */

                $status = $row->status;
                if ($status != Person::ACTIVE && $status != Person::INACTIVE && $status != Person::INACTIVE_EXTENSION) {
                    $canContact = 'none';
                } else {
                    $canContact = $row->use_email ? 'allow' : 'block';
                }

                $years[$year][$menteeId] = [
                    'person_id' => $menteeId,
                    'callsign' => $row->callsign,
                    'status' => $status,
                    'formerly_known_as' => implode(', ', PersonFka::filterOutIrrelevant($fkas->get($menteeId)?->pluck('fka')->toArray())),
                    'contact_status' => $canContact,
                    'mentor_status' => $row->mentor_status,
                    'profile_url' => $photos->get($menteeId)?->profile_url,
                    'last_worked' => $lastYears->get($menteeId)?->year,
                    'last_contact' => $lastContact->get($menteeId)?->sent_at,
                    'mentors' => []
                ];
            }

            $years[$year][$menteeId]['mentors'][] = [
                'callsign' => $row->mentor_callsign,
                'person_id' => $row->mentor_id,
            ];
        }

        $result = [];

        foreach ($years as $year => $mentees) {
            $people = [];
            $passed = 0;
            $bonked = 0;
            foreach ($mentees as $menteeId => $mentee) {
                $people[] = $mentee;

                if ($mentee['mentor_status'] == 'pass') {
                    $passed++;
                } else {
                    $bonked++;
                }
            }
            $result[] = [
                'year' => $year,
                'mentees' => $people,
                'passed' => $passed,
                'bonked' => $bonked
            ];
        }

        return $result;
    }

    /**
     * Find the mentors for a person
     *
     * @param int $personId
     * @param $allHistories
     * @return array
     */

    public static function retrieveMentorHistory(int $personId, $allHistories): array
    {
        $history = $allHistories->get($personId);
        if (!$history) {
            return [];
        }

        $history = $history->groupBy('mentor_year');

        $summary = [];
        foreach ($history as $year => $mentors) {
            $people = [];
            foreach ($mentors as $mentor) {
                $people[] = [
                    'id' => $mentor->mentor_id,
                    'callsign' => $mentor->mentor->callsign,
                    'profile_url' => $mentor->mentor->person_photo?->profile_url,
                    'person_mentor_id' => $mentor->id
                ];
            }

            $summary[] = [
                'year' => $year,
                'status' => $mentors[0]->status,
                'mentors' => $people
            ];
        }

        usort($summary, fn($a, $b) => $b['year'] - $a['year']);

        return $summary;
    }

    public static function retrieveBulkMentorHistory($peopleIds)
    {
        return PersonMentor::with(['mentor:id,callsign,person_photo_id', 'mentor.person_photo'])
            ->whereIn('person_id', $peopleIds)
            ->get()
            ->sortBy('mentor.callsign', SORT_NATURAL | SORT_FLAG_CASE)
            ->groupBy('person_id');
    }


    /**
     *  Bulk retrieve the entire mentor history up to and including the given year
     *
     * @param array $peopleIds people to find their mentor history
     * @param integer $year mentor years to find up to and including
     * @return array
     */

    public static function retrieveAllMentorsForIds(array $peopleIds, int $year): array
    {
        $peopleGroups = self::whereIntegerInRaw('person_id', $peopleIds)
            ->with('mentor:id,callsign')
            ->orderBy('person_id')
            ->orderBy('mentor_year')
            ->get()
            ->groupBy('person_id');

        $mentees = [];

        foreach ($peopleGroups as $personId => $mentors) {
            $mentorsByYear = [];
            $person = $mentors[0];

            foreach ($mentors as $mentor) {
                $year = $mentor->mentor_year;
                $pm = $mentor->mentor;
                if (!isset($mentorsByYear[$year])) {
                    $mentorsByYear[$year] = [
                        'status' => $mentor->status,
                        'mentors' => []
                    ];
                }
                $mentorsByYear[$year]['mentors'][] = [
                    'id' => $mentor->mentor_id,
                    'callsign' => $pm?->callsign ?? "Deleted #{$mentor->mentor_id}"
                ];
            }

            foreach (array_keys($mentorsByYear) as $year) {
                usort($mentorsByYear[$year]['mentors'], fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));
            }
            $mentees[$personId] = $mentorsByYear;
        }

        return $mentees;
    }
}

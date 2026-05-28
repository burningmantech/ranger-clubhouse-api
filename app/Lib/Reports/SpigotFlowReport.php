<?php

namespace App\Lib\Reports;

use App\Models\ActionLog;
use App\Models\Person;
use App\Models\PersonOnlineCourse;
use App\Models\PersonPhoto;
use App\Models\PersonSlot;
use App\Models\PersonStatus;
use App\Models\Position;
use App\Models\ProspectiveApplication;
use App\Models\Slot;
use App\Models\TraineeStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SpigotFlowReport
{
    /**
     * Prior to this year the SoR for PNV applications was Salesforce; the Clubhouse only
     * saw approved accounts, so the 'imported' and 'created' counts are identical.
     */
    private const int SALESFORCE_CUTOVER_YEAR = 2025;

    /** Bucket key used for photo approvals dated outside the reporting year. */
    private const string PREVIOUS_YEAR_KEY = '0';

    private Carbon $yearStart;
    private Carbon $yearEnd;

    /** @var array<string, array<string, array<int, int|string>>> */
    private array $dates = [];

    /** @var array<int, Carbon> personId => imported_at timestamp */
    private array $pnvImportedAt = [];

    /** @var int[] */
    private array $pnvIds = [];

    /** @var array<int, string> personId => callsign */
    private array $personMap = [];

    public static function execute(int $year): array
    {
        return (new self())->build($year);
    }

    private function build(int $year): array
    {
        $this->yearStart = Carbon::create($year, 1, 1);
        $this->yearEnd = Carbon::create($year + 1, 1, 1);

        if (!$this->loadProspectives($year)) {
            return [];
        }

        $this->hydratePersonMap();
        $this->loadFirstLogins();
        $this->loadDroppedStatuses();
        $this->loadApprovedPhotos();
        $this->loadOnlineTraining($year);
        $this->loadTrainingActivity($year);
        $this->loadAlphaSignups($year);

        return $this->formatDays();
    }

    private function loadProspectives(int $year): bool
    {
        return $year < self::SALESFORCE_CUTOVER_YEAR
            ? $this->loadProspectivesFromPersonStatus()
            : $this->loadProspectivesFromApplications();
    }

    /**
     * Pre-2025: only approved accounts existed in the Clubhouse, so imported == created.
     * groupBy guards against rare duplicate conversions producing repeat rows.
     */
    private function loadProspectivesFromPersonStatus(): bool
    {
        $grouped = PersonStatus::select('person_id', 'created_at')
            ->whereBetween('created_at', [$this->yearStart, $this->yearEnd])
            ->where('new_status', Person::PROSPECTIVE)
            ->orderBy('created_at')
            ->get()
            ->groupBy('person_id');

        if ($grouped->isEmpty()) {
            return false;
        }

        foreach ($grouped as $personId => $rows) {
            $first = $rows[0];
            $this->recordDate('imported', $first->created_at, $personId);
            $this->recordDate('created', $first->created_at, $personId);
            $this->pnvImportedAt[$personId] = $first->created_at;
            $this->pnvIds[] = $personId;
        }
        return true;
    }

    /**
     * 2025+: every application is stored. 'imported' counts all submissions; 'created'
     * counts only those that actually became Clubhouse accounts (rejected, duplicate,
     * or returning Shiny Penny applications are filtered out).
     */
    private function loadProspectivesFromApplications(): bool
    {
        $applications = ProspectiveApplication::select('person_id', 'created_at', 'status')
            ->whereBetween('created_at', [$this->yearStart, $this->yearEnd])
            ->orderBy('created_at')
            ->get();

        if ($applications->isEmpty()) {
            return false;
        }

        foreach ($applications as $application) {
            $this->recordDate('imported', $application->created_at, $application->person_id);
            if ($application->status === ProspectiveApplication::STATUS_CREATED) {
                $this->recordDate('created', $application->created_at, $application->person_id);
                $this->pnvImportedAt[$application->person_id] = $application->created_at;
                $this->pnvIds[] = $application->person_id;
            }
        }
        return true;
    }

    /**
     * Build the id => callsign lookup for the year's PNV cohort once, so per-stage
     * loaders can skip ->with('person:...') and avoid rehydrating Person models.
     */
    private function hydratePersonMap(): void
    {
        $this->personMap = Person::whereIntegerInRaw('id', $this->pnvIds)
            ->pluck('callsign', 'id')
            ->all();
    }

    /**
     * Pull the first auth-login per PNV directly via MIN() rather than dragging every
     * login event into PHP. The fallback covers the rare case where the earliest in-year
     * login predates the (re)import — for those we need the full ordered list.
     */
    private function loadFirstLogins(): void
    {
        $aggregates = ActionLog::selectRaw('person_id, MIN(created_at) as first_login')
            ->whereIntegerInRaw('person_id', $this->pnvIds)
            ->whereBetween('created_at', [$this->yearStart, $this->yearEnd])
            ->where('event', 'auth-login')
            ->groupBy('person_id')
            ->get();

        $fallbackIds = [];
        foreach ($aggregates as $row) {
            $importedAt = $this->pnvImportedAt[$row->person_id];
            $first = Carbon::parse($row->first_login);
            if ($first->gte($importedAt)) {
                $this->recordDate('first_login', $first, $row->person_id);
            } else {
                $fallbackIds[] = $row->person_id;
            }
        }

        if (empty($fallbackIds)) {
            return;
        }

        $followup = ActionLog::select('person_id', 'created_at')
            ->whereIntegerInRaw('person_id', $fallbackIds)
            ->whereBetween('created_at', [$this->yearStart, $this->yearEnd])
            ->where('event', 'auth-login')
            ->orderBy('created_at')
            ->get()
            ->groupBy('person_id');

        foreach ($followup as $personId => $rows) {
            $importedAt = $this->pnvImportedAt[$personId];
            foreach ($rows as $login) {
                if ($login->created_at->gte($importedAt)) {
                    $this->recordDate('first_login', $login->created_at, $personId);
                    break;
                }
            }
        }
    }

    private function loadDroppedStatuses(): void
    {
        $grouped = PersonStatus::select('person_id', 'created_at', 'new_status')
            ->whereBetween('created_at', [$this->yearStart, $this->yearEnd])
            ->where('new_status', Person::PAST_PROSPECTIVE)
            ->whereIntegerInRaw('person_id', $this->pnvIds)
            ->orderBy('created_at')
            ->get()
            ->groupBy('person_id');

        $this->recordFirstPerPerson('dropped', $grouped, fn($row) => $row->created_at);
    }

    private function loadApprovedPhotos(): void
    {
        $photos = PersonPhoto::select('person_photo.id', 'person_photo.person_id', 'person_photo.uploaded_at', 'person_photo.reviewed_at')
            ->where('person_photo.status', PersonPhoto::APPROVED)
            ->join('person', 'person.person_photo_id', 'person_photo.id')
            ->whereIntegerInRaw('person_photo.person_id', $this->pnvIds)
            ->get();

        foreach ($photos as $photo) {
            // Photos prior to 2020 have no reviewed_at — Lambase didn't expose it.
            $date = $photo->reviewed_at ?? $photo->uploaded_at;
            if ($date !== null && $date->gte($this->yearStart) && $date->lt($this->yearEnd)) {
                $this->recordDate('photo_approved', $date, $photo->person_id);
            } else {
                $this->recordInPreviousBucket('photo_approved', $photo->person_id);
            }
        }
    }

    private function loadOnlineTraining(int $year): void
    {
        $completions = PersonOnlineCourse::select('person_id', 'completed_at')
            ->whereIntegerInRaw('person_id', $this->pnvIds)
            ->where('year', $year)
            ->where('position_id', Position::TRAINING)
            ->whereNotNull('completed_at')
            ->get();

        foreach ($completions as $completion) {
            $this->recordDate('online_trained', $completion->completed_at, $completion->person_id);
        }
    }

    private function loadTrainingActivity(int $year): void
    {
        $slotIds = $this->slotIdsFor(Position::TRAINING, $year);
        if (empty($slotIds)) {
            return;
        }

        $signups = PersonSlot::whereIntegerInRaw('person_id', $this->pnvIds)
            ->whereIntegerInRaw('slot_id', $slotIds)
            ->get()
            ->groupBy('person_id');
        $this->recordFirstPerPerson('training_signups', $signups, fn($row) => $row->created_at);

        $passes = TraineeStatus::select('trainee_status.*', 'slot.begins')
            ->join('slot', 'slot.id', 'trainee_status.slot_id')
            ->whereIntegerInRaw('person_id', $this->pnvIds)
            ->whereIntegerInRaw('slot_id', $slotIds)
            ->where('passed', true)
            ->get()
            ->groupBy('person_id');
        $this->recordFirstPerPerson('training_passed', $passes, fn($row) => $row->begins);
    }

    private function loadAlphaSignups(int $year): void
    {
        $slotIds = $this->slotIdsFor(Position::ALPHA, $year);
        if (empty($slotIds)) {
            return;
        }

        $signups = PersonSlot::whereIntegerInRaw('person_id', $this->pnvIds)
            ->whereIntegerInRaw('slot_id', $slotIds)
            ->get()
            ->groupBy('person_id');
        $this->recordFirstPerPerson('alpha_signups', $signups, fn($row) => $row->created_at);
    }

    /** @return int[] */
    private function slotIdsFor(int $positionId, int $year): array
    {
        return Slot::where('position_id', $positionId)
            ->where('begins_year', $year)
            ->pluck('id')
            ->toArray();
    }

    private function recordFirstPerPerson(string $stage, Collection $grouped, callable $dateAccessor): void
    {
        foreach ($grouped as $personId => $rows) {
            $first = $rows[0];
            $this->recordDate($stage, $dateAccessor($first), $personId);
        }
    }

    private function recordDate(string $stage, mixed $date, int|string|null $personId): void
    {
        $this->dates[$this->normalizeDay($date)][$stage][] = $personId ?? '';
    }

    private function recordInPreviousBucket(string $stage, int|string|null $personId): void
    {
        $this->dates[self::PREVIOUS_YEAR_KEY][$stage][] = $personId ?? '';
    }

    private function normalizeDay(mixed $date): string
    {
        if ($date instanceof Carbon) {
            return $date->format('Y-m-d');
        }
        if (is_numeric($date)) {
            return (new Carbon($date))->format('Y-m-d');
        }
        return Carbon::parse((string) $date)->format('Y-m-d');
    }

    /** @return array{id: int|string, callsign: string} */
    private function describePerson(int|string $personId): array
    {
        // Deleted account, rejected application, or id outside the cohort — keep the slot but blank the identity.
        if ($personId === '' || !isset($this->personMap[$personId])) {
            return ['id' => '', 'callsign' => ''];
        }
        return ['id' => $personId, 'callsign' => $this->personMap[$personId]];
    }

    /** @return array<int, array<string, mixed>> */
    private function formatDays(): array
    {
        $days = [];
        foreach ($this->dates as $day => $stages) {
            $resolved = [];
            foreach ($stages as $stage => $personIds) {
                $people = array_map(fn($id) => $this->describePerson($id), $personIds);
                usort($people, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));
                $resolved[$stage] = $people;
            }
            $resolved['day'] = $day;
            $days[] = $resolved;
        }

        usort($days, fn($a, $b) => strcmp($a['day'], $b['day']));
        return $days;
    }
}

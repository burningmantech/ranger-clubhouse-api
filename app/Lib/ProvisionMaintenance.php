<?php

namespace App\Lib;

use App\Models\Bmid;
use App\Models\Provision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProvisionMaintenance
{
    private const string REASON_EXPIRED        = 'marked as expired via maintenance function';
    private const string REASON_USED           = 'marked as used via maintenance function';
    private const string REASON_BANKED         = 'marked as banked via bank maintenance function';
    private const string REASON_AVAILABLE      = 'marked as available via maintenance function';
    private const string REASON_RADIO_EXPIRED  = 'unclaimed event radio marked as expired via bank maintenance function';
    private const string REASON_UNBANK         = 'maintenance - unbank provisions';
    private const string REASON_CLEAN_EXPIRED  = 'expired via clean maintenance function';
    private const string REASON_RADIO_UNUSED   = 'was not banked, did not work, and did not check out an event radio. expired via clean provision maintenance function.';
    private const string REASON_UNSUBMIT       = 'un-submit maintenance function';

    private const array UNALLOCATED_LIVE_STATUSES = [
        Provision::BANKED, Provision::CLAIMED, Provision::AVAILABLE,
    ];

    private const array CONSUMABLE_STATUSES = [
        Provision::SUBMITTED, Provision::CLAIMED,
    ];

    /**
     * Expire all non-allocated provisions with status banked, claimed, or available
     * whose expiry date has passed.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function expire(): array
    {
        $actor = self::actorCallsign();

        return DB::transaction(function () use ($actor) {
            $rows = self::baseQuery()
                ->whereIn('status', self::UNALLOCATED_LIVE_STATUSES)
                ->where('is_allocated', false)
                ->where('expires_on', '<=', now())
                ->get();

            $results = $rows->map(function (Provision $p) use ($actor) {
                $p->status = Provision::EXPIRED;
                $p->auditReason = self::REASON_EXPIRED;
                $p->addComment(self::REASON_EXPIRED, $actor);
                return self::saveAndDescribe($p, includeEmail: true);
            })->all();

            return self::sortByCallsign($results);
        });
    }

    /**
     * Unbank all currently banked provisions, returning them to available status.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function unbankProvisions(): array
    {
        $actor = self::actorCallsign();

        return DB::transaction(function () use ($actor) {
            $rows = self::baseQuery()
                ->where('status', Provision::BANKED)
                ->get();

            $results = $rows->map(function (Provision $row) use ($actor) {
                $row->status = Provision::AVAILABLE;
                $row->auditReason = self::REASON_UNBANK;
                $row->addComment(self::REASON_AVAILABLE, $actor);
                return self::saveAndDescribe($row, includeEmail: true, withoutValidation: true);
            })->all();

            return self::sortByCallsign($results);
        });
    }

    /**
     * Bank all unallocated available provisions. Unclaimed event radios are expired instead.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function bankProvisions(): array
    {
        $actor = self::actorCallsign();

        return DB::transaction(function () use ($actor) {
            $rows = self::baseQuery()
                ->where('status', Provision::AVAILABLE)
                ->where('is_allocated', false)
                ->get();

            $results = $rows->map(function (Provision $p) use ($actor) {
                if ($p->type === Provision::EVENT_RADIO) {
                    $p->status = Provision::EXPIRED;
                    $p->addComment(self::REASON_RADIO_EXPIRED, $actor);
                } else {
                    $p->status = Provision::BANKED;
                    $p->addComment(self::REASON_BANKED, $actor);
                }
                return self::saveAndDescribe($p);
            })->all();

            return self::sortByCallsign($results);
        });
    }

    /**
     * Consume or expire provisions from the prior event.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function cleanProvisionsFromPriorEvent(): array
    {
        $actor = self::actorCallsign();
        $year = maintenance_year();

        return DB::transaction(function () use ($actor, $year) {
            $results = array_merge(
                self::cleanUnallocatedProvisions($actor, $year),
                self::cleanAllocatedProvisions($actor, $year),
            );
            return self::sortByCallsign($results);
        });
    }

    /**
     * Move non-allocated claimed/submitted provisions back to available for the given people.
     *
     * @param int[] $peopleIds
     */
    public static function unsubmitProvisions(array $peopleIds): void
    {
        if (empty($peopleIds)) {
            return;
        }

        DB::transaction(function () use ($peopleIds) {
            Provision::whereIntegerInRaw('person_id', $peopleIds)
                ->whereIn('status', self::CONSUMABLE_STATUSES)
                ->where('is_allocated', false)
                ->get()
                ->each(function (Provision $p) {
                    $p->status = Provision::AVAILABLE;
                    $p->auditReason = self::REASON_UNSUBMIT;
                    $p->additional_comments = self::REASON_UNSUBMIT;
                    $p->save();
                });
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function cleanUnallocatedProvisions(string $actor, int $year): array
    {
        $rows = self::baseQuery()
            ->where('is_allocated', false)
            ->whereIn('status', [Provision::AVAILABLE, Provision::CLAIMED, Provision::SUBMITTED])
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $hasAvailableRadios = $rows->contains(
            fn(Provision $p) => $p->status === Provision::AVAILABLE && $p->type === Provision::EVENT_RADIO,
        );
        $work = $hasAvailableRadios
            ? self::loadWorkActivity($rows->pluck('person_id')->unique()->all(), $year)
            : null;

        $results = [];
        foreach ($rows as $prov) {
            $entry = self::consumeOrExpireUnallocated($prov, $actor, $year, $work);
            if ($entry !== null) {
                $results[] = $entry;
            }
        }
        return $results;
    }

    /**
     * @param array{timesheets: Collection, radios: Collection}|null $work
     * @return array<string, mixed>|null
     */
    private static function consumeOrExpireUnallocated(
        Provision $prov,
        string $actor,
        int $year,
        ?array $work,
    ): ?array {
        $reason = self::REASON_USED;

        if ($prov->status === Provision::AVAILABLE) {
            if ($prov->type !== Provision::EVENT_RADIO) {
                // Other available provisions can be rolled over to the next event.
                return null;
            }

            $reasons = [];
            if ($work !== null && $work['timesheets']->has($prov->person_id)) {
                $reasons[] = 'person worked';
            }
            if ($work !== null && $work['radios']->has($prov->person_id)) {
                $reasons[] = 'checked out radio';
            }

            if (empty($reasons)) {
                $prov->status = Provision::EXPIRED;
                $prov->auditReason = self::REASON_CLEAN_EXPIRED;
                $prov->addComment(self::REASON_RADIO_UNUSED, $actor);
                return self::saveAndDescribe($prov);
            }

            $reason = self::REASON_USED . ' - reason ' . implode(', ', $reasons);
        }

        $prov->status = Provision::USED;
        $prov->consumed_year = $year;
        $prov->auditReason = $reason;
        $prov->addComment($reason, $actor);
        return self::saveAndDescribe($prov);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function cleanAllocatedProvisions(string $actor, int $year): array
    {
        $rows = self::baseQuery()
            ->where('is_allocated', true)
            ->whereIn('status', [
                Provision::AVAILABLE,
                Provision::CLAIMED,
                Provision::SUBMITTED,
                Provision::BANKED,
            ])->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $submittedBmids = Bmid::whereIntegerInRaw('person_id', $rows->pluck('person_id')->unique())
            ->where('year', $year)
            ->where('status', Bmid::SUBMITTED)
            ->get()
            ->keyBy('person_id');

        return $rows->map(function (Provision $row) use ($actor, $year, $submittedBmids) {
            $consumed = $submittedBmids->has($row->person_id)
                || in_array($row->status, self::CONSUMABLE_STATUSES, true);

            if ($consumed) {
                $row->status = Provision::USED;
                $row->consumed_year = $year;
                $row->auditReason = self::REASON_USED;
                $row->addComment(self::REASON_USED, $actor);
            } else {
                $row->status = Provision::EXPIRED;
                $row->auditReason = self::REASON_EXPIRED;
                $row->addComment(self::REASON_EXPIRED, $actor);
            }
            return self::saveAndDescribe($row);
        })->all();
    }

    /**
     * @param int[] $personIds
     * @return array{timesheets: Collection, radios: Collection}
     */
    private static function loadWorkActivity(array $personIds, int $year): array
    {
        $timesheets = DB::table('timesheet')
            ->whereIntegerInRaw('person_id', $personIds)
            ->whereYear('on_duty', $year)
            ->distinct('person_id')
            ->get()
            ->keyBy('person_id');

        $radios = DB::table('asset_person')
            ->join('asset', 'asset.id', 'asset_person.asset_id')
            ->whereIntegerInRaw('person_id', $personIds)
            ->whereYear('checked_out', $year)
            ->where('asset.description', 'Radio')
            ->distinct('person_id')
            ->get()
            ->keyBy('person_id');

        return compact('timesheets', 'radios');
    }

    private static function baseQuery(): Builder
    {
        return Provision::query()->with('person:id,callsign,status,email');
    }

    /**
     * Save the provision and return a response shape for the maintenance result.
     *
     * @return array<string, mixed>
     */
    private static function saveAndDescribe(
        Provision $p,
        bool $includeEmail = false,
        bool $withoutValidation = false,
    ): array {
        $withoutValidation ? $p->saveWithoutValidation() : $p->save();

        $person = $p->person;
        $result = [
            'id'          => $p->id,
            'type'        => $p->type,
            'status'      => $p->status,
            'source_year' => $p->source_year,
            'person' => [
                'id'       => $p->person_id,
                'callsign' => $person?->callsign ?? ('Person #' . $p->person_id),
                'status'   => $person?->status ?? 'unknown',
            ],
        ];

        if ($includeEmail && $person) {
            $result['person']['email'] = $person->email;
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $provisions
     * @return array<int, array<string, mixed>>
     */
    private static function sortByCallsign(array $provisions): array
    {
        usort(
            $provisions,
            fn($a, $b) => strcasecmp($a['person']['callsign'], $b['person']['callsign']),
        );
        return $provisions;
    }

    private static function actorCallsign(): string
    {
        return Auth::user()?->callsign ?? 'system';
    }
}

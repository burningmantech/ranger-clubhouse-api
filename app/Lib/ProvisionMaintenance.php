<?php

namespace App\Lib;

use App\Models\Bmid;
use App\Models\Provision;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProvisionMaintenance
{
    /**
     * Expire all non-allocated provisions with status banked, claimed, or available.
     *
     * @return array
     */

    public static function expire(): array
    {
        $user = Auth::user()->callsign;

        $rows = Provision::whereIn('status', [Provision::BANKED, Provision::CLAIMED, Provision::AVAILABLE])
            ->where('is_allocated', false)
            ->where('expires_on', '<=', now())
            ->with('person:id,callsign,status,email')
            ->get();

        $provisions = [];
        foreach ($rows as $p) {
            $p->status = Provision::EXPIRED;
            $p->addComment('marked as expired via maintenance function', $user);
            self::saveProvision($p, $provisions, true);
        }

        self::sortProvisions($provisions);

        return $provisions;
    }

    /**
     * Unbank all provisions
     *
     * @return array
     */

    public static function unbankProvisions(): array
    {
        $user = Auth::user()->callsign;
        $rows = Provision::where('status', Provision::BANKED)
            ->with('person:id,callsign,status')
            ->get();

        $provisions = [];
        foreach ($rows as $row) {
            $row->auditReason = 'maintenance - unbank provisions';
            $row->status = Provision::AVAILABLE;
            $row->addComment('marked as available via maintenance function', $user);
            $row->saveWithoutValidation();
            self::saveProvision($row, $provisions, true);
        }

        self::sortProvisions($provisions);

        return $provisions;
    }

    /**
     * Bank the provisions
     *
     * @return array
     */

    public static function bankProvisions(): array
    {
        $user = Auth::user()->callsign;

        $rows = Provision::where('status', Provision::AVAILABLE)
            ->where('is_allocated', false)
            ->with('person:id,callsign,status')
            ->get();

        $provisions = [];
        foreach ($rows as $p) {
            if ($p->type == Provision::EVENT_RADIO) {
                $p->status = Provision::EXPIRED;
                $p->addComment('unclaimed event radio marked as expired via bank maintenance function', $user);
            }
            self::saveProvision($p, $provisions);
        }

        self::sortProvisions($provisions);

        return $provisions;
    }

    /**
     * Clean / consume provisions from prior event.
     *
     * Anyone who worked will have the provisions consumed.
     *
     * @return array
     */

    public static function cleanProvisionsFromPriorEvent(): array
    {
        $user = Auth::user()->callsign;
        $maintenanceYear = maintenance_year();

        $rows = Provision::where('is_allocated', false)
            ->whereIn('status', [Provision::AVAILABLE, Provision::CLAIMED, Provision::SUBMITTED])
            ->with('person:id,callsign,status')
            ->get();

        if ($rows->isEmpty()) {
            $timesheets = null;
            $radios = null;
        } else {
            $peopleIds = $rows->pluck('person_id')->unique()->toArray();
            $timesheets = DB::table('timesheet')
                ->whereIntegerInRaw('person_id', $peopleIds)
                ->whereYear('on_duty', $maintenanceYear)
                ->distinct('person_id')
                ->get()
                ->keyBy('person_id');

            $radios = DB::table('asset_person')
                ->join('asset', 'asset.id', 'asset_person.asset_id')
                ->whereIntegerInRaw('person_id', $peopleIds)
                ->whereYear('checked_out', $maintenanceYear)
                ->where('asset.description', 'Radio')
                ->distinct('person_id')
                ->get()
                ->keyBy('person_id');
        }

        $provisions = [];

        $reasonExpired = 'marked as expired via maintenance function';
        $reasonUsed = 'marked as used via maintenance function';

        foreach ($rows as $prov) {
            if ($prov->status == Provision::AVAILABLE) {
                if ($prov->type != Provision::EVENT_RADIO) {
                    // Other available provisions can be rolled over to the next event.
                    continue;
                }

                $reasons = [];
                if ($timesheets->has($prov->person_id)) {
                    $reasons[] = 'person worked';
                }

                if ($radios->has($prov->person_id)) {
                    $reasons[] = 'checked out radio';
                }

                if (empty($reasons)) {
                    // Person did not work and/or checked out a radio.
                    $prov->status = Provision::EXPIRED;
                    $prov->addComment('was not banked, did not work and/or did not check out an event radio. expired via clean provision maintenance function.', $user);
                    $prov->auditReason = 'expired via clean maintenance function';
                    self::saveProvision($prov, $provisions);
                    continue;
                }
                $reason = $reasonUsed . ' - reason ' . join(', ', $reasons);
            } else {
                $reason = $reasonUsed;
            }

            $prov->status = Provision::USED;
            $prov->consumed_year = $maintenanceYear;
            $prov->addComment($reason, $user);
            $prov->auditReason = $reason;
            self::saveProvision($prov, $provisions);
        }

        // Expire or use all job provisions.
        $rows = Provision::where('is_allocated', true)
            ->whereIn('status', [Provision::AVAILABLE, Provision::CLAIMED, Provision::SUBMITTED, Provision::BANKED])
            ->with('person:id,callsign,status')
            ->get();

        if ($rows->isEmpty()) {
            return $provisions;
        }

        $bmids = Bmid::whereIntegerInRaw('person_id', $rows->pluck('person_id')->unique())
            ->where('year', $maintenanceYear)
            ->where('status', Bmid::SUBMITTED)
            ->get()
            ->keyBy('person_id');

        foreach ($rows as $row) {
            if ($bmids->has($row->person_id) || $row->status == Provision::SUBMITTED || $row->status == Provision::CLAIMED) {
                $row->status = Provision::USED;
                $row->consumed_year = $maintenanceYear;
                $row->addComment($reasonUsed, $user);
                $row->auditReason = $reasonUsed;
            } else {
                $row->status = Provision::EXPIRED;
                $row->addComment($reasonExpired, $user);
                $row->auditReason = $reasonExpired;
            }
            self::saveProvision($row, $provisions);
        }

        self::sortProvisions($provisions);

        return $provisions;
    }

    /**
     * Unsubmit (set as available) all non-allocated claimed & submitted provisions.
     *
     * @param array $peopleIds
     * @return void
     */

    public static function unsubmitProvisions(array $peopleIds): void
    {
        foreach ($peopleIds as $personId) {
            $provisions = Provision::where('person_id', $personId)
                ->whereIn('status', [Provision::CLAIMED, Provision::SUBMITTED])
                ->where('is_allocated', false)
                ->get();

            foreach ($provisions as $provision) {
                $provision->status = Provision::AVAILABLE;
                $provision->auditReason = 'un-submit maintenance function';
                $provision->additional_comments = 'un-submit maintenance function';
                $provision->save();
            }
        }
    }


    /**
     * Save the provision, log the changes, and build a response.
     *
     * @param Provision $p
     * @param $provisions
     * @param bool $includeEmail
     */

    public static function saveProvision(Provision $p, &$provisions, bool $includeEmail = false): void
    {
        $p->save();

        $person = $p->person;
        $result = [
            'id' => $p->id,
            'type' => $p->type,
            'status' => $p->status,
            'source_year' => $p->source_year,
            'person' => [
                'id' => $p->person_id,
                'callsign' => $person->callsign ?? ('Person #' . $p->person_id),
                'status' => $person->status ?? 'unknown',
            ]
        ];

        if ($includeEmail && $person) {
            $result['person']['email'] = $person->email;
        }

        $provisions[] = $result;
    }

    public static function sortProvisions(array &$provisions): void
    {
        usort($provisions, fn($a, $b) => strcasecmp($a['person']['callsign'], $b['person']['callsign']));
    }
}
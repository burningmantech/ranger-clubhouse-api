<?php

namespace App\Lib;

use App\Models\AccessDocument;

class TicketingStatistics
{
    public static function execute(): array
    {
        return [
            'people_claimed' => AccessDocument::where('status', AccessDocument::CLAIMED)
                ->whereIn('type', AccessDocument::TICKET_TYPES)
                ->distinct()
                ->count('person_id'),
            'people_fully_banked' => AccessDocument::where('status', AccessDocument::BANKED)
                ->whereIn('type', AccessDocument::TICKET_TYPES)
                ->whereRaw("NOT EXISTS (select 1 from access_document as claimed WHERE claimed.person_id=access_document.person_id and claimed.status='claimed' LIMIT 1)")
                ->distinct()
                ->count('access_document.person_id'),
            'people_tickets_unclaimed' => AccessDocument::where('status', AccessDocument::QUALIFIED)
                ->whereIn('type', AccessDocument::TICKET_TYPES)
                ->whereRaw("NOT EXISTS (select 1 from access_document as claimed WHERE claimed.person_id=access_document.person_id and (claimed.status='claimed' or claimed.status = 'banked') LIMIT 1)")
                ->distinct()
                ->count('access_document.person_id'),

            'people_tickets_submitted' => AccessDocument::where('status', AccessDocument::SUBMITTED)
                ->whereIn('type', AccessDocument::TICKET_TYPES)
                ->whereRaw("NOT EXISTS (select 1 from access_document as claimed WHERE claimed.person_id=access_document.person_id and (claimed.status='claimed' or claimed.status = 'banked') LIMIT 1)")
                ->distinct()
                ->count('access_document.person_id'),

            'rpt_claimed' => self::countStatusType(AccessDocument::CLAIMED, AccessDocument::RPT),
            'rpt_banked' => self::countStatusType(AccessDocument::BANKED, AccessDocument::RPT),
            'rpt_unclaimed' => self::countStatusType(AccessDocument::QUALIFIED, AccessDocument::RPT),
            'rpt_submitted' => self::countStatusType(AccessDocument::SUBMITTED, AccessDocument::RPT),

            'sc_claimed' => self::countStatusType(AccessDocument::CLAIMED, AccessDocument::STAFF_CREDENTIAL),
            'sc_banked' => self::countStatusType(AccessDocument::BANKED, AccessDocument::STAFF_CREDENTIAL),
            'sc_unclaimed' => self::countStatusType(AccessDocument::QUALIFIED, AccessDocument::STAFF_CREDENTIAL),
            'sc_submitted' => self::countStatusType(AccessDocument::SUBMITTED, AccessDocument::STAFF_CREDENTIAL),

            'vp_claimed' => self::countStatusType(AccessDocument::CLAIMED, AccessDocument::VEHICLE_PASS),
            'vp_unclaimed' => self::countStatusType(AccessDocument::QUALIFIED, AccessDocument::VEHICLE_PASS),
            'vp_submitted' => self::countStatusType(AccessDocument::SUBMITTED, AccessDocument::VEHICLE_PASS),

            'wap_claimed' => self::countStatusType(AccessDocument::CLAIMED, AccessDocument::WAP),
            'wap_unclaimed' => self::countStatusType(AccessDocument::QUALIFIED, AccessDocument::WAP),
            'wap_submitted' => self::countStatusType(AccessDocument::SUBMITTED, AccessDocument::WAP),

            'wapso_claimed' => self::countStatusType(AccessDocument::CLAIMED, AccessDocument::WAPSO),
            'wapso_submitted' => self::countStatusType(AccessDocument::SUBMITTED, AccessDocument::WAPSO),
            'people_with_wapso' => AccessDocument::where('status', AccessDocument::CLAIMED)
                ->where('type', AccessDocument::WAPSO)
                ->distinct()
                ->count('person_id'),

            'people_with_qualified_provisions' => AccessDocument::where('status', AccessDocument::QUALIFIED)
                ->whereIn('type', AccessDocument::PROVISION_TYPES)
                ->where('is_allocated', false)
                ->distinct()
                ->count('person_id'),

            'people_with_banked_provisions' => AccessDocument::where('status', AccessDocument::BANKED)
                ->whereIn('type', AccessDocument::PROVISION_TYPES)
                ->distinct()
                ->count('person_id'),

            'people_with_allocated_provisions' => AccessDocument::whereIn('type', AccessDocument::PROVISION_TYPES)
                ->where('status', AccessDocument::QUALIFIED)
                ->where('is_allocated', true)
                ->distinct()
                ->count('person_id'),

            'people_with_both_provisions' => AccessDocument::whereIn('type', AccessDocument::PROVISION_TYPES)
                ->whereNotIn('status', AccessDocument::INVALID_STATUSES)
                ->whereExists(function ($sql) {
                    $sql->selectRaw(1)
                        ->from('access_document as ad')
                        ->whereColumn('ad.person_id', 'access_document.person_id')
                        ->whereIn('ad.type', AccessDocument::PROVISION_TYPES)
                        ->whereNotIn('ad.status', AccessDocument::INVALID_STATUSES)
                        ->whereColumn('ad.is_allocated', '!=', 'access_document.is_allocated')
                        ->limit(1);
                })
                ->distinct()
                ->count('person_id'),
        ];

    }

    public static function countStatusType($status, $type): int
    {
        return AccessDocument::where('status', $status)->where('type', $type)->count();
    }
}
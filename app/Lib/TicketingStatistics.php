<?php

namespace App\Lib;

use App\Models\AccessDocument;
use App\Models\Provision;

class TicketingStatistics
{
    public static function execute(): array
    {
        return [
            'people_claimed' => AccessDocument::where('status', AccessDocument::CLAIMED)
                ->whereIn('type', AccessDocument::REGULAR_TICKET_TYPES)
                ->distinct()
                ->count('person_id'),
            'people_fully_banked' => AccessDocument::where('status', AccessDocument::BANKED)
                ->whereIn('type', AccessDocument::REGULAR_TICKET_TYPES)
                ->whereRaw("NOT EXISTS (select 1 from access_document as claimed WHERE claimed.person_id=access_document.person_id and claimed.status='claimed' LIMIT 1)")
                ->distinct()
                ->count('access_document.person_id'),
            'people_tickets_unclaimed' => AccessDocument::where('status', AccessDocument::QUALIFIED)
                ->whereIn('type', AccessDocument::REGULAR_TICKET_TYPES)
                ->whereRaw("NOT EXISTS (select 1 from access_document as claimed WHERE claimed.person_id=access_document.person_id and (claimed.status='claimed' or claimed.status = 'banked') LIMIT 1)")
                ->distinct()
                ->count('access_document.person_id'),

            'people_tickets_submitted' => AccessDocument::where('status', AccessDocument::SUBMITTED)
                ->whereIn('type', AccessDocument::REGULAR_TICKET_TYPES)
                ->distinct()
                ->count('access_document.person_id'),

            'spt_claimed' => self::countStatusType(AccessDocument::CLAIMED, AccessDocument::SPT),
            'spt_banked' => self::countStatusType(AccessDocument::BANKED, AccessDocument::SPT),
            'spt_unclaimed' => self::countStatusType(AccessDocument::QUALIFIED, AccessDocument::SPT),
            'spt_submitted' => self::countStatusType(AccessDocument::SUBMITTED, AccessDocument::SPT),

            'gift_claimed' => self::countStatusType(AccessDocument::CLAIMED, AccessDocument::GIFT),
            'gift_unclaimed' => self::countStatusType(AccessDocument::QUALIFIED, AccessDocument::GIFT),
            'gift_submitted' => self::countStatusType(AccessDocument::SUBMITTED, AccessDocument::GIFT),

            'gift_vp_claimed' => self::countStatusType(AccessDocument::CLAIMED, AccessDocument::VEHICLE_PASS_GIFT),
            'gift_vp_unclaimed' => self::countStatusType(AccessDocument::QUALIFIED, AccessDocument::VEHICLE_PASS_GIFT),
            'gift_vp_submitted' => self::countStatusType(AccessDocument::SUBMITTED, AccessDocument::VEHICLE_PASS_GIFT),

            'lsd_claimed' => self::countStatusType(AccessDocument::CLAIMED, AccessDocument::LSD),
            'lsd_unclaimed' => self::countStatusType(AccessDocument::QUALIFIED, AccessDocument::LSD),
            'lsd_submitted' => self::countStatusType(AccessDocument::SUBMITTED, AccessDocument::LSD),

            'lsd_vp_claimed' => self::countStatusType(AccessDocument::CLAIMED, AccessDocument::VEHICLE_PASS_LSD),
            'lsd_vp_unclaimed' => self::countStatusType(AccessDocument::QUALIFIED, AccessDocument::VEHICLE_PASS_LSD),
            'lsd_vp_submitted' => self::countStatusType(AccessDocument::SUBMITTED, AccessDocument::VEHICLE_PASS_LSD),

            'sc_claimed' => self::countStatusType(AccessDocument::CLAIMED, AccessDocument::STAFF_CREDENTIAL),
            'sc_banked' => self::countStatusType(AccessDocument::BANKED, AccessDocument::STAFF_CREDENTIAL),
            'sc_unclaimed' => self::countStatusType(AccessDocument::QUALIFIED, AccessDocument::STAFF_CREDENTIAL),
            'sc_submitted' => self::countStatusType(AccessDocument::SUBMITTED, AccessDocument::STAFF_CREDENTIAL),

            'sp_vp_claimed' => self::countStatusType(AccessDocument::CLAIMED, AccessDocument::VEHICLE_PASS_SP),
            'sp_vp_unclaimed' => self::countStatusType(AccessDocument::QUALIFIED, AccessDocument::VEHICLE_PASS_SP),
            'sp_vp_submitted' => self::countStatusType(AccessDocument::SUBMITTED, AccessDocument::VEHICLE_PASS_SP),

            'wap_claimed' => self::countStatusType(AccessDocument::CLAIMED, AccessDocument::WAP),
            'wap_unclaimed' => self::countStatusType(AccessDocument::QUALIFIED, AccessDocument::WAP),
            'wap_submitted' => self::countStatusType(AccessDocument::SUBMITTED, AccessDocument::WAP),

            'wapso_claimed' => self::countStatusType(AccessDocument::CLAIMED, AccessDocument::WAPSO),
            'wapso_submitted' => self::countStatusType(AccessDocument::SUBMITTED, AccessDocument::WAPSO),
            'people_with_wapso' => AccessDocument::where('status', AccessDocument::CLAIMED)
                ->where('type', AccessDocument::WAPSO)
                ->distinct()
                ->count('person_id'),

            'people_with_qualified_provisions' => Provision::where('status', Provision::AVAILABLE)
                ->where('is_allocated', false)
                ->distinct()
                ->count('person_id'),

            'people_with_banked_provisions' => Provision::where('status', Provision::BANKED)
                ->distinct()
                ->count('person_id'),

            'people_with_allocated_provisions' => Provision::whereIn('status', [ Provision::AVAILABLE, Provision::SUBMITTED])
                ->where('is_allocated', true)
                ->distinct()
                ->count('person_id'),

            'people_with_both_provisions' => Provision::whereNotIn('status', Provision::INVALID_STATUSES)
                ->whereExists(function ($sql) {
                    $sql->selectRaw(1)
                        ->from('provision as p')
                        ->whereColumn('p.person_id', 'provision.person_id')
                        ->whereNotIn('p.status', AccessDocument::INVALID_STATUSES)
                        ->whereColumn('p.is_allocated', '!=', 'provision.is_allocated')
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
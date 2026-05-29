<?php

namespace App\Lib\BulkUpload;

use App\Models\AccessDocument;

/**
 * Two-to-six letter codes that the bulk-upload tickets action accepts in
 * the second CSV column (e.g. CRED, SPT, GIFT, VPLSD). Each maps to an
 * AccessDocument type plus the rules for which optional columns may
 * follow it.
 */
enum TicketTypeCode: string
{
    case Cred   = 'CRED';
    case Spt    = 'SPT';
    case Lsd    = 'LSD';
    case Gift   = 'GIFT';
    case Vp     = 'VP';
    case VpGift = 'VPGIFT';
    case VpLsd  = 'VPLSD';
    case Wap    = 'WAP';
    case Sap    = 'SAP';

    public function accessDocumentType(): string
    {
        return match ($this) {
            self::Cred           => AccessDocument::STAFF_CREDENTIAL,
            self::Spt            => AccessDocument::SPT,
            self::Lsd            => AccessDocument::LSD,
            self::Gift           => AccessDocument::GIFT,
            self::Vp             => AccessDocument::VEHICLE_PASS_SP,
            self::VpGift         => AccessDocument::VEHICLE_PASS_GIFT,
            self::VpLsd          => AccessDocument::VEHICLE_PASS_LSD,
            self::Wap, self::Sap => AccessDocument::WAP,
        };
    }

    /**
     * Some codes (LSD, GIFT, VPGIFT, VPLSD, WAP, SAP) override the default
     * source year with the current event year regardless of when the
     * upload happens.
     */
    public function sourceYearOverride(): ?int
    {
        return match ($this) {
            self::Lsd, self::Gift, self::VpGift, self::VpLsd, self::Wap, self::Sap => current_year(),
            default => null,
        };
    }

    public function acceptsAccessDate(): bool
    {
        return $this === self::Cred || $this === self::Wap || $this === self::Sap;
    }

    public function acceptsExpiryYear(): bool
    {
        return $this === self::Spt || $this === self::Cred;
    }

    public static function tryFromInput(string $input): ?self
    {
        return self::tryFrom(strtoupper(trim($input)));
    }
}

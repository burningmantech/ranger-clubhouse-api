<?php

namespace App\Lib;

use App\Models\ActionLog;
use App\Models\Document;
use App\Models\Person;
use App\Models\PersonEvent;
use InvalidArgumentException;

/*
 * TODO: Move to a flag/certificate/agreement tracking system to layer on top of. This is intended to be a
 * temporary abstraction.
 */

class Agreements
{
    const TERM_LIFETIME = 'lifetime';   // column stored in person
    const TERM_ANNUAL = 'annual';       // column stored in person_event

    /**
     * A document list available to most Rangers and PNVs.
     *
     * key: the document tag identifier (see App/Models/Document)
     * term: indicates the agreement is lifetime (person table col.) or annual (person_event table col.)
     * setting: the Clubhouse setting indicating if the document is available to sign by all non-auditor accounts.
     * person_event: the person_event column indicating if the document is available to the user to sign.
     *
     * NOTE: if neither setting nor person_event is present, this indicates the document is available at all times.
     *
     */

    const DOCUMENTS = [
        'behavioral-standards-agreement' => [
            'term' => self::TERM_LIFETIME,
            'column' => 'behavioral_agreement',
        ],

        'motorpool-policy' => [
            'term' => self::TERM_ANNUAL,
            'setting' => 'MotorpoolPolicyEnable',
            'column' => 'signed_motorpool_agreement',
        ],

        'personal-vehicle-agreement' => [
            'term' => self::TERM_ANNUAL,
            'person_event' => 'may_request_stickers',
            'column' => 'signed_personal_vehicle_agreement',
        ],

        'radio-checkout-agreement' => [
            'setting' => 'RadioCheckoutAgreementEnabled',
            'term' => self::TERM_ANNUAL,
            'column' => 'asset_authorized',
        ]
    ];

    /**
     * Retrieve all available agreements the person may see but not necessarily sign (yet).
     *
     * @param Person $person
     * @return array
     */

    public static function retrieve(Person $person): array
    {
        $agreements = [];
        $personEvent = PersonEvent::firstOrNewForPersonYear($person->id, current_year());

        foreach (self::DOCUMENTS as $tag => $paper) {
            $term = $paper['term'];

            if (!self::isDocumentVisible($paper, $personEvent)) {
                continue;
            }

            $document = Document::findIdOrTag($tag);

            if (!$document) {
                continue;
            }

            $col = $paper['column'];
            $agreements[] = [
                'tag' => $tag,
                'title' => $document->description,
                'signed' => (bool)($term == self::TERM_LIFETIME ? $person->{$col} : $personEvent->{$col}),
                'available' => self::canSignDocument($person->id, $tag, $personEvent)
            ];
        }

        usort($agreements, fn($a, $b) => strcasecmp($a['title'], $b['title']));

        return $agreements;
    }

    /**
     * Digitally sign an agreement
     *
     * @param Person $person
     * @param string $tag
     * @param bool $signature
     */

    public static function signAgreement(Person $person, string $tag, bool $signature)
    {
        $paper = self::DOCUMENTS[$tag] ?? null;
        if (!$paper) {
            throw new InvalidArgumentException('Document tag not found');
        }

        $personEvent = PersonEvent::firstOrNewForPersonYear($person->id, current_year());

        if (!self::canSignDocument($person->id, $tag, $personEvent)) {
            throw new InvalidArgumentException('Document not available to sign');
        }

        $col = $paper['column'];
        if ($paper['term'] == self::TERM_LIFETIME) {
            $person->{$col} = $signature;
            $person->saveWithoutValidation();
        } else {
            $personEvent->{$col} = $signature;
            $personEvent->saveWithoutValidation();
        }

        ActionLog::record($person, 'agreement-signature', null, [
            'tag' => $tag,
            'signature' => $signature
        ]);
    }

    /**
     * Check to see if an agreement has been signed.
     *
     * @param Person $person
     * @param string $tag
     * @param null $personEvent
     * @return bool
     */

    public static function obtainSignature(Person $person, string $tag, $personEvent = null): bool
    {
        $paper = self::DOCUMENTS[$tag] ?? null;
        if (!$paper) {
            throw new InvalidArgumentException('Document tag not found');
        }

        $col = $paper['column'];
        if ($paper['term'] == self::TERM_LIFETIME) {
            return $person->{$col};
        } else {
            if (!$personEvent) {
                $personEvent = PersonEvent::firstOrNewForPersonYear($person->id, current_year());
            }
            return $personEvent->{$col};
        }
    }

    /**
     * Check to see if the agreement can be signed.
     *
     * @param int $personId
     * @param string $tag
     * @param null $personEvent
     * @return bool
     */
    public static function canSignDocument(int $personId, string $tag, $personEvent = null): bool
    {
        $paper = self::DOCUMENTS[$tag] ?? null;
        if (!$paper) {
            throw new InvalidArgumentException('Document tag not found');
        }

        $setting = $paper['setting'] ?? null;
        $peColumn = $paper['person_event'] ?? null;

        if ($setting) {
            return setting($setting);
        } else if ($peColumn) {
            if (!$personEvent) {
                $personEvent = PersonEvent::firstOrNewForPersonYear($personId, current_year());
            }
            return $personEvent->{$peColumn};
        } else {
            return true;
        }
    }

    /**
     * Is the document visible? (however, it may not be ready to be signed)
     *
     * @param $doc
     * @param $personEvent
     * @return bool
     */

    public static function isDocumentVisible($doc, $personEvent): bool
    {
        $peColumn = $doc['person_event'] ?? null;

        if ($doc['setting'] ?? null) {
            return true;
        } else if ($peColumn) {
            return $personEvent->{$peColumn};
        } else {
            return true;
        }
    }
}
<?php

namespace App\Lib;

use App\Models\AccessDocument;
use App\Models\Bmid;
use App\Models\PersonEvent;
use App\Models\Provision;
use App\Models\Timesheet;

class TicketAndProvisionsPackage
{
    /**
     * Build up a ticketing package for the person
     *
     * @param int $personId
     * @return array
     */

    public static function buildPackageForPerson(int $personId): array
    {
        $year = event_year() - 1;
        if ($year == 2020 || $year == 2021) {
            // 2020 & 2021 didn't happen. :-(
            $year = 2019;
        }

        $pe = PersonEvent::firstOrNewForPersonYear($personId, current_year());

        $accessDocuments = [];

        $giftItems = [];
        $lsdItems = [];

        $ads = AccessDocument::findForQuery(['person_id' => $personId]);
        foreach ($ads as $ad) {
            switch ($ad->type) {
                case AccessDocument::GIFT:
                case AccessDocument::VEHICLE_PASS_GIFT:
                    if ($ad->isAvailable()) {
                        $giftItems[] = $ad;
                    }
                    break;

                case AccessDocument::LSD:
                case AccessDocument::VEHICLE_PASS_LSD:
                    if ($ad->isAvailable()) {
                        $lsdItems[] = $ad;
                    }
                    break;

                default:
                    $accessDocuments[] = $ad;
            }
        }

        $result = [
            'access_documents' => $accessDocuments,
            'gift_items' => $giftItems,
            'lsd_items' => $lsdItems,
            'special_tickets' => [],  // TODO: Remove beginning of May '23. Just in case the most recent frontend is not being used.
            'provisions' => Provision::findForQuery(['person_id' => $personId]),
            'credits_earned' => Timesheet::earnedCreditsForYear($personId, $year),
            'year_earned' => $year,
            'period' => setting('TicketingPeriod'),
            'started_at' => $pe->ticketing_started_at ? (string)$pe->ticketing_started_at : null,
            'finished_at' => $pe->ticketing_finished_at ? (string)$pe->ticketing_finished_at : null,
            'visited_at' => $pe->ticketing_last_visited_at ? ( string)$pe->ticketing_last_visited_at : null,
        ];

        self::buildProvisions($personId, $result);

        return $result;
    }

    /**
     * Build up the provisions package.
     * - Combine all provisions types to a single effective type
     * - Indicate if the provisions can be banked, i.e., no allocated provisions present
     * - Provide an expiry date on earned (non-allocated) types. Assume only one earned type will be present.
     *   (at present multiple provision types are awarded, i.e. no earned double meals, showers, etc.)
     *
     * @param int $personId
     * @param $result
     * @return void
     */

    public static function buildProvisions(int $personId, &$result): void
    {
        $provisions = Provision::findForQuery(['person_id' => $personId]);

        $mealMatrix = [];
        $haveAllocated = false;
        $radios = 0;
        $haveShowers = false;
        $haveBanked = false;
        $mealsExpire = null;
        $showersExpire = null;
        $radioExpires = null;

        foreach ($provisions as $provision) {
            if ($provision->is_allocated) {
                $haveAllocated = true;
            } else if ($provision->status == Provision::BANKED) {
                $haveBanked = true;
            }
            $isMeal = in_array($provision->type, Provision::MEAL_TYPES);
            if ($isMeal) {
                Bmid::populateMealMatrix(Provision::MEAL_MATRIX[$provision->type], $mealMatrix);
                if (!$haveAllocated) {
                    $mealsExpire = (string)$provision->expires_on;
                }
                continue;
            }

            if ($provision->type == Provision::EVENT_RADIO) {
                if ($provision->item_count > $radios) {
                    $radios = $provision->item_count;
                }
                if (!$haveAllocated) {
                    $radioExpires = (string)$provision->expires_on;
                }
            } else if ($provision->type == Provision::WET_SPOT) {
                $haveShowers = true;
                if (!$haveAllocated) {
                    $showersExpire = (string)$provision->expires_on;
                }
            }
        }

        $meals = !empty($mealMatrix) ? Bmid::sortMeals($mealMatrix) : null;

        if ($meals || $radios || $haveShowers) {
            $stuff = [
                'meals' => $meals,
                'radios' => $radios,
                'showers' => $haveShowers,
            ];
            if (!$haveAllocated) {
                $stuff['meals_expire'] = $mealsExpire;
                $stuff['showers_expire'] = $showersExpire;
                $stuff['radio_expire'] = $radioExpires;
            }
            $result['provisions'] = $stuff;
            $result['provisions_bankable'] = !$haveAllocated;
            $result['provisions_banked'] = $haveBanked;
        }
    }

}
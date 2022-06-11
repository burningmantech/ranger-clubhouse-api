<?php

namespace App\Lib;

use App\Lib\Reports\TimesheetWorkSummaryReport;
use App\Models\Person;
use App\Models\Schedule;
use App\Models\Timesheet;

class TicketsAndProvisionsProgress
{
    const MEASURE_CREDITS = 'credits';
    const MEASURE_HOURS = 'hours';

    const ITEM_STAFF_CREDENTIAL = 'staff_credential';
    const ITEM_RPT = 'rpt';

    const ITEM_SHOWER_POG = 'shower_pog';
    const ITEM_SHOWER_ACCESS = 'shower_access';

    const ITEM_EVENT_WEEK_MEAL_PASS = 'event_week_eats';
    const ITEM_ALL_EAT_PASS = 'all_eats';

    const ITEM_TSHIRT = 't_shirt';
    const ITEM_LONG_SLEEVE_SHIRT = 'long_sleeve_shirt';

    public $items = [];
    public $other_duration = 0;

    /**
     * Figure out what the tickets and provisions (meals, showers, clothing) the person
     * has earned based on work done already, what they might be expected to earn based on remaining
     * scheduled signups, and how much credits/hours they are short in signups.
     *
     * @param Person $person
     * @return TicketsAndProvisionsProgress
     */

    public static function compute(Person $person): TicketsAndProvisionsProgress
    {
        $year = current_year();
        $personId = $person->id;

        $thresholds = setting([
            'AllYouCanEatEventWeekThreshold',
            'AllYouCanEatEventPeriodThreshold',
            'ScTicketThreshold',
            'RpTicketThreshold',
            'ShowerPogThreshold',
            'ShowerAccessThreshold',
            'ShirtLongSleeveHoursThreshold',
            'ShirtShortSleeveHoursThreshold',
        ]);

        $timesheetSummary = TimesheetWorkSummaryReport::execute($personId, $year);
        $remaining = Schedule::scheduleSummaryForPersonYear($personId, $year, true);

        $credits = $timesheetSummary->total_credits;
        $expectedCredits = $credits + $remaining->total_credits;
        $hours = $timesheetSummary->counted_duration;
        $expectedHours = $hours + $remaining->total_duration;
        $preEventAndEventHours = $timesheetSummary->pre_event_duration + $timesheetSummary->event_duration;
        $expectedPreEventAndEventHours = $preEventAndEventHours + $remaining->pre_event_duration + $remaining->event_duration;

        $progress = new self;

        $progress->calculateItem(self::ITEM_RPT,
            $credits, $expectedCredits,
            $thresholds['RpTicketThreshold'], self::MEASURE_CREDITS);

        $progress->calculateItem(self::ITEM_STAFF_CREDENTIAL,
            $credits, $expectedCredits,
            $thresholds['ScTicketThreshold'], self::MEASURE_CREDITS);

        $progress->calculateItem(self::ITEM_ALL_EAT_PASS,
            $hours, $expectedHours,
            $thresholds['AllYouCanEatEventPeriodThreshold'], self::MEASURE_HOURS);

        $progress->calculateItem(self::ITEM_EVENT_WEEK_MEAL_PASS,
            $timesheetSummary->event_duration, $timesheetSummary->event_duration + $remaining->event_duration,
            $thresholds['AllYouCanEatEventWeekThreshold'], self::MEASURE_HOURS);

        $progress->calculateItem(self::ITEM_SHOWER_POG,
            $timesheetSummary->event_duration, $timesheetSummary->event_duration + $remaining->event_duration,
            $thresholds['ShowerPogThreshold'], self::MEASURE_HOURS);

        $progress->calculateItem(self::ITEM_SHOWER_ACCESS,
            $timesheetSummary->event_duration, $timesheetSummary->event_duration + $remaining->event_duration,
            $thresholds['ShowerAccessThreshold'], self::MEASURE_HOURS);

        $progress->calculateItem(self::ITEM_TSHIRT,
            $preEventAndEventHours, $expectedPreEventAndEventHours,
            $thresholds['ShirtShortSleeveHoursThreshold'], self::MEASURE_HOURS);

        $progress->calculateItem(self::ITEM_LONG_SLEEVE_SHIRT,
            $preEventAndEventHours, $expectedPreEventAndEventHours,
            $thresholds['ShirtLongSleeveHoursThreshold'], self::MEASURE_HOURS);


        $progress->other_duration = $timesheetSummary->other_duration;
        $progress->is_shiny_penny = Timesheet::hasAlphaEntry($personId) && $person->status == Person::ACTIVE;

        return $progress;
    }

    public function calculateItem($item, $value, $expectedValue, $threshold, $measure)
    {
        if ($measure === self::MEASURE_HOURS) {
            $thresholdAdjusted = $threshold * 3600;
        } else {
            $thresholdAdjusted = $threshold;
        }

        $didEarn = ($value >= $thresholdAdjusted);
        $expectedEarn = ($expectedValue >= $thresholdAdjusted);
        $this->items[] = [
            'name' => $item,
            'measure' => $measure,
            'value' => $value,
            'expected_total' => $expectedValue,
            'threshold' => $threshold,
            'has_earned' => $didEarn,
            'expected_to_earn' => $expectedEarn,
            'needs_to_work' => $didEarn ? 0 : ($thresholdAdjusted - $value),
            'needs_to_schedule' => ($didEarn || $expectedEarn) ? 0 : ($thresholdAdjusted - $expectedValue),
        ];
    }
}
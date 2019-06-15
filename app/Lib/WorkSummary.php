<?php

namespace App\Lib;

use App\Models\PositionCredit;

class WorkSummary
{
    public $event_start;
    public $event_end;
    public $year;

    public $pre_event_credits  = 0;
    public $event_credits      = 0;
    public $post_event_credits = 0;
    public $pre_event_duration  = 0;
    public $event_duration      = 0;
    public $post_event_duration = 0;

    public $other_duration = 0;

    public function __construct($event_start, $event_end, $year)
    {
        $this->event_start = $event_start;
        $this->event_end = $event_end;
        $this->year = $year;
    }

    public function computeTotals($position_id, $on_duty, $off_duty, $count_hours)
    {
        /*
         * The following bits were adopted from the old Hours and Credits report written by Socrates back in 2014.
         */

        if (!$count_hours) {
            // Dump time into other category if hours are not to be counted.
            $this->other_duration += ($off_duty - $on_duty);
        }

        if ($on_duty < $this->event_start) {   /* on duty before event */
            if ($off_duty < $this->event_start) {  /* on-before & off-duty before */
                $this->pre_event_credits +=  PositionCredit::computeCredits($position_id, $on_duty, $off_duty, $this->year);
                if ($count_hours) {
                    $this->pre_event_duration  += ($off_duty - $on_duty);
                }
            }

            if (($off_duty >= $this->event_start) and ($off_duty <= $this->event_end)) { /* on_duty before event and off_duty during event */
                $this->pre_event_credits  +=  PositionCredit::computeCredits($position_id, $on_duty, $this->event_start, $this->year);
                $this->event_credits      +=  PositionCredit::computeCredits($position_id, $this->event_start, $off_duty, $this->year);

                if ($count_hours) {
                    $this->pre_event_duration += ($this->event_start - $on_duty);
                    $this->event_duration     += ($off_duty - $this->event_start);
                }
            }

            if ($off_duty > $this->event_end) { /*on duty before event and off duty after event (aka: needs a life) */
                $this->pre_event_credits  += PositionCredit::computeCredits($position_id, $on_duty, $this->event_start, $this->year);
                $this->event_credits      += PositionCredit::computeCredits($position_id, $this->event_start, $this->event_end, $this->year);
                $this->post_event_credits += PositionCredit::computeCredits($position_id, $this->event_end, $off_duty, $this->year);

                if ($count_hours) {
                    $this->pre_event_duration  += ($this->event_start - $on_duty);
                    $this->event_duration      += ($this->event_end - $this->event_start);
                    $this->post_event_duration += ($off_duty - $this->event_end);
                }
            }
        } // end if (on duty before event)

        if (($on_duty >= $this->event_start) and ($on_duty <= $this->event_end)) { /* on duty during event */
            if ($off_duty <= $this->event_end) {
                $this->event_credits += PositionCredit::computeCredits($position_id, $on_duty, $off_duty, $this->year);
                if ($count_hours) {
                    $this->event_duration += ($off_duty - $on_duty);
                }
            }
            if ($off_duty > $this->event_end) {
                $this->event_credits      += PositionCredit::computeCredits($position_id, $on_duty, $this->event_end, $this->year);
                $this->post_event_credits += PositionCredit::computeCredits($position_id, $this->event_end, $off_duty, $this->year);
                if ($count_hours) {
                    $this->event_duration += ($this->event_end - $on_duty);
                    $this->post_event_duration += ($off_duty - $this->event_end);
                }
            }
        } // end if (on duty during event
        if ($on_duty > $this->event_end) { /* on duty after event */
            $this->post_event_credits += PositionCredit::computeCredits($position_id, $on_duty, $off_duty, $this->year);
            if ($count_hours) {
                $this->post_event_duration += ($off_duty - $on_duty);
            }
        }
    }
}

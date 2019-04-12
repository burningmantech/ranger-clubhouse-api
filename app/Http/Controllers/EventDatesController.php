<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\EventDate;

class EventDatesController extends ApiController
{
    /**
     * Show all the event dates
     */

    public function index()
    {
        $dates = EventDate::findAll();

        return $this->success($dates, null, 'event_dates');
    }

    /**
     * Store a newly created event date
     */
    public function store(Request $request)
    {
        $this->authorize('store', EventDate::class);
        $event_date = new EventDate;
        $this->fromRest($event_date);

        if (!$event_date->save()) {
            return $this->restError($event_date);
        }

        return $this->success($event_date);
    }

    /**
     * Display the event date
     *
     */
    public function show(EventDate $event_date)
    {
        return $this->success($event_date);
    }

    /**
     * Show for year
     */

    public function showYear()
    {
        $params = request()->validate([
            'year'  => 'required|integer'
        ]);

        return response()->json([ 'event_date' => EventDate::findForYear($params['year']) ]);
    }

    /**
     * Update the event date
     *
     */
    public function update(EventDate $event_date)
    {
        $this->authorize('update', EventDate::class);
        $this->fromRest($event_date);

        if (!$event_date->save()) {
            return $this->restError($event_date);
        }

        return $this->success($event_date);
    }

    /**
     * Remove an event date
     */
    public function destroy(EventDate $event_date)
    {
        $this->authorize('delete', $event_date);
        $event_date->delete();
        return $this->restDeleteSuccess();
    }
}

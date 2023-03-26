<?php

namespace App\Http\Controllers;

use App\Models\EventDate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class EventDatesController extends ApiController
{
    /**
     * Show all the event dates
     */

    public function index(): JsonResponse
    {
        $dates = EventDate::findAll();

        return $this->success($dates, null, 'event_dates');
    }

    /**
     * Store a newly created event date
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function store(): JsonResponse
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
     */

    public function show(EventDate $event_date): JsonResponse
    {
        return $this->success($event_date);
    }

    /**
     * Show for year
     *
     * @return JsonResponse
     */

    public function showYear(): JsonResponse
    {
        $params = request()->validate([
            'year' => 'required|integer'
        ]);

        return response()->json(['event_date' => EventDate::findForYear($params['year'])]);
    }

    /**
     * Update the event date
     *
     * @param EventDate $event_date
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function update(EventDate $event_date): JsonResponse
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
     *
     * @param EventDate $event_date
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(EventDate $event_date): JsonResponse
    {
        $this->authorize('delete', $event_date);
        $event_date->delete();
        return $this->restDeleteSuccess();
    }

    /**
     * Retrieve the current event period.
     *
     * @return JsonResponse
     */

    public function period(): JsonResponse
    {
        return response()->json(['period' => EventDate::retrieveEventOpsPeriod()]);
    }
}

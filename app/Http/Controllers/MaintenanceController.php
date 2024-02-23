<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MaintenanceController extends ApiController
{
    /**
     * Mark everyone who is on site as off site.
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function markOffSite(): JsonResponse
    {
        $this->authorize('isAdmin');

        // Grab the folks who are going to be marked as off site
        $people = Person::select('id', 'callsign')->where('on_site', true)->get();

        // .. and actually mark them. (would be nice if Eloquent could have a get() then update() feature)
        Person::where('on_site', true)->update(['on_site' => false]);

        // Log what was done
        foreach ($people as $person) {
            $this->log('person-update', 'maintenance - marked off site', ['id' => $person->id, 'on_site' => [true, false]], $person->id);
        }

        return response()->json(['count' => $people->count()]);
    }

    /**
     * Reset all PNVs (Alphas/Bonks/Prospecitves) to Past Prospective status, reset callsign, and marked unapproved.
     * @return JsonResponse
     */

    public function resetPNVs(): JsonResponse
    {
        Gate::allowIf(fn ($user) => $user->hasRole([Role::ADMIN, Role::VC]));

        $pnvs = Person::whereIn('status', [Person::ALPHA, Person::BONKED, Person::PROSPECTIVE, Person::PROSPECTIVE_WAITLIST])
            ->orderBy('callsign')
            ->get();

        $people = [];
        foreach ($pnvs as $person) {
            $result = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'status' => $person->status
            ];

            if ($person->resetCallsign()) {
                $person->callsign_approved = false;
                $person->status = Person::PAST_PROSPECTIVE;
                $person->auditReason = 'maintenance - pnv reset';
                $person->saveWithoutValidation();
                $result['callsign_reset'] = $person->callsign;
            } else {
                $result['error'] = 'Cannot reset callsign';
            }
            $people[] = $result;
        }

        return response()->json(['people' => $people]);
    }

    /**
     * Reset all Past Prospectives with approved callsigns to LastFirstYY and mark the callsign as unapproved.
     * @return JsonResponse
     */

    public function resetPassProspectives(): JsonResponse
    {
        Gate::allowIf(fn ($user) => $user->hasRole([Role::ADMIN, Role::VC]));

        $pp = Person::where('status', Person::PAST_PROSPECTIVE)
            ->where('callsign_approved', true)
            ->orderBy('callsign')
            ->get();

        $people = [];
        foreach ($pp as $person) {
            $result = [
                'id' => $person->id,
                'callsign' => $person->callsign,
                'status' => $person->status
            ];

            if ($person->resetCallsign()) {
                $person->callsign_approved = false;
                $changes = $person->getChangedValues();
                $person->auditReason = 'maintenance - past prospective reset';
                $person->saveWithoutValidation();
                $result['callsign_reset'] = $person->callsign;
            } else {
                $result['error'] = 'Cannot reset callsign';
            }
            $people[] = $result;
        }

        return response()->json(['people' => $people]);
    }

    /**
     * Archive Clubhouse messages
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function archiveMessages(): JsonResponse
    {
        prevent_if_ghd_server('Message archiving');
        $this->authorize('isAdmin');

        $year = current_year();
        $prevYear = $year - 1;
        $table = "person_message_$prevYear";

        $exists = count(DB::select("SHOW TABLES LIKE '$table'"));

        if ($exists) {
            return response()->json(['status' => 'archive-exists', 'year' => $prevYear]);
        }

        DB::statement("CREATE TABLE $table AS SELECT * FROM person_message");
        DB::table('person_message')->whereYear('created_at', '<', $year)->delete();

        $this->log('archive-messages', "archive messages $prevYear into table $table", null);

        return response()->json(['status' => 'success', 'year' => $prevYear]);
    }
}

<x-html-email>
    <p>
        Hey {{$application->first_name}} {{$application->last_name}},
    </p>
    <p>
        The Ranger Volunteer Coordinators have a question regarding your Ranger application.
    </p>
    <x-vc-application-on-hold />
    <p>
        Which Burning Man events have you attended? Please note that the 2020 and 2021 events do not count towards
        the attendance qualification. Additionally, you must have attended Burning Man at least once in the last 10
        events, excluding 2020 and 2021.
    </p>
    <p>
        Your Friendly Black Rock Ranger Volunteer Coordinators
    </p>
    <x-vc-application-footer :application="$application" />
</x-html-email>

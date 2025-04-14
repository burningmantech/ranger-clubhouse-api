<x-html-email>
    <p>
        Hey {{$application->first_name}} {{$application->last_name}},
    </p>
    <p>
        The Ranger Volunteer Coordinators have a question regarding your Ranger application.
    </p>
    <x-vc-application-on-hold/>
    <p>
        You indicated on your Ranger Application that you are under 18 years old. Will you turn 18 before, or on the
        day of, your Alpha shift on playa?
    </p>
    <p>
        An Alpha shift is a mentor evaluation shift that takes place on playa. These shifts occur on the Saturday before
        the gate opens, as well as on Sunday, Monday, and Tuesday. Note, only one shift needs to be attended.
    </p>
    <p>
        Your Friendly Black Rock Ranger Volunteer Coordinators
    </p>
    <x-vc-application-footer :application="$application" />
</x-html-email>

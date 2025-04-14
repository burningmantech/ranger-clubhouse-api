<x-html-email>
    <p>
        Hey {{$application->first_name}} {{$application->last_name}},
    </p>
    <p>
        The Ranger Volunteer Coordinators have been reviewing your application and noticed some information is missing.
    </p>
    <x-vc-application-on-hold />
    <p>
        The following field(s) are blank on your application:
    </p>
    <p>
        @foreach($application->blankPersonalInfo() as $field)
            {{$field}}<br>
        @endforeach
    </p>
    <p>
        Reply to this message with the missing information.
    </p>
    <p>
        Your Friendly Black Rock Ranger Volunteer Coordinators
    </p>
    <x-vc-application-footer :application="$application" />
</x-html-email>

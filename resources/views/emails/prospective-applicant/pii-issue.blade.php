<x-html-email>
    <p>
        Hey {{$application->first_name}} {{$application->last_name}},
    </p>
    <p>
        The Ranger Volunteer Coordinators have been reviewing your application and noticed some information is missing.
        <b style="color: red;">Your application is on hold until we hear back from you.</b>
    </p>
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
    <p>
        <b>Questions?</b> Email <a href="mailto:ranger-vc-list@burningman.org">ranger-vc-list@burningman.org</a>
    </p>
    Application ID A-{{$application->id}}
</x-html-email>

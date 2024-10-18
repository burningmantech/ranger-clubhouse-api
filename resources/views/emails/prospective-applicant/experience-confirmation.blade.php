<x-html-email>
    <p>
        Hey {{$application->first_name}} {{$application->last_name}},
    </p>
    <p>
        The Ranger Volunteer Coordinators have a question regarding your Ranger application.
        <b style="color: red;">Your application is on hold until we hear back from you.</b>
    </p>
    <p>
        Which Burning Man events have you attended? Please note that 2020 and 2021 do not count toward the
        event attendance qualification.
    </p>
    <p>
        Your Friendly Black Rock Ranger Volunteer Coordinators
    </p>
    <p>
        <b>Questions?</b> <a href="mailto:ranger-vc-list@burningman.org">ranger-vc-list@burningman.org</a>
    </p>
    Application ID A-{{$application->id}}
</x-html-email>

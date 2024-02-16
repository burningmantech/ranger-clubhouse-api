<x-html-email>
    <p>
        Hey {{$application->first_name}} {{$application->last_name}},
    </p>
    <p>
        The Ranger Volunteer Coordinators have a question regarding your Ranger application.
        <b style="color: red;">Your application is on hold until we hear back from you.</b>
    </p>
    <p>
        What are all the official Burning Man events have you attended?
    </p>
     <p>
        Your Friendly Black Rock Ranger Volunteer Coordinators
    </p>
    <p>
        Email <a href="mailto:ranger-vc-list@burningman.org">ranger-vc-list@burningman.org</a>
    </p>
    Application ID A-{{$application->id}}
</x-html-email>

<x-html-email>
    <p>
        Hey {{$application->first_name}} {{$application->last_name}},
    </p>
    <p>
        The Ranger Volunteer Coordinators have reviewed your application and have a question about your response to the <i>Why Do You Want to Ranger?</i> section.
        <b style="color: red;">Your application is on hold until we hear back from you.</b>
    </p>
    <p>
        Here is the message from the Volunteer Coordinators:
    </p>
    <p>
        {!! nl2br($messageToUser) !!}
    </p>
    <p>
        Please respond to this message promptly to avoid delays in processing your application.
        Failure to do so in a timely manner may result in your application being withdrawn.
    </p>
    <p>
        Your Friendly Black Rock Ranger Volunteer Coordinators
    </p>
    <p>
        <b>Questions?</b> Email <a href="mailto:ranger-vc-list@burningman.org">ranger-vc-list@burningman.org</a>
    </p>
    Application ID A-{{$application->id}}
</x-html-email>

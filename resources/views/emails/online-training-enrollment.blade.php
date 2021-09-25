<x-html-email :isPublicEmail="true">
<p>
        Hello {{$person->callsign}},
    </p>
    <p>
        This confirms a Ranger Online Training website account has been created and you have been enrolled in
        Part 1 of Ranger Training (online). The account is SEPARATE from your Clubhouse account.
    </p>
    <p>
        In order to log into the Online Training website, use the following credentials:
    </p>
    <p>
        <b>Username: </b> {{$person->email}}
    </p>
    <p>
        <b>Password: </b> {{$password}}
    </p>
    <p>
        <b>Online training website: </b> <a href="$otUrl">{{$otUrl}}</a>
    </p>
    <p>
        Note: the above credentials are ONLY for the online training website (lms.burningman.org), NOT the
        Clubhouse.
    </p>

    <p>
        <b>Training Questions?</b> Contact the Ranger Training Academy at
        <a href="mailto:ranger-trainingacademy-list@burningman.org">ranger-trainingacademy-list@burningman.org</a>
    </p>
    <p>
        <b>Other Questions?</b> Contact the Ranger Volunteer Coordinators at
        <a href="mailto:ranger-vc-list@burningman.org">ranger-vc-list@burningman.org</a>
    </p>
    <p>
        Yours sincerely,<br>
        The Ranger Training Academy
    </p>
</x-html-email>

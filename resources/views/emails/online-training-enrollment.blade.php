<x-html-email :isPublicEmail="true">
    <p>
        Hello {{$person->callsign}},
    </p>
    <p>
        This confirms a Ranger Online Training website account has been created,
        and you have been enrolled in the online course.
    </p>
    <p>
        <b>The Online Training account is SEPARATE from your Clubhouse account.</b>
    </p>
    <p>
        In order to log into the Online Training website, use the following credentials:
    </p>
    <p>
        <b>Username: </b> {{$person->lms_username}}<br>
        Your username is your callsign in all lower case and ONLY has letters (a-z) and numbers (0-9). All spaces, dashes, and
        other special characters have been stripped out.
    </p>
    <p>
        <b>Password: </b> {{$password}}
    </p>
    <p>
        <b>The Online Course Training website: </b> <a href="$otUrl">{{$otUrl}}</a>
    </p>
    <p>
        Note: the above credentials are ONLY for the online training website, NOT the Clubhouse.
    </p>
    <p>
        After completing the online course, you will need to attend the In-Person Training.
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

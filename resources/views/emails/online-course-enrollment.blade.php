<x-html-email :isPublicEmail="true">
    <p>
        Hello {{$person->callsign}},
    </p>
    <p>
        This confirms an Online Course website account has been created, and you have been enrolled in the Online
        Course.
    </p>
    <p>
        After completing the Online Course, you will need to attend an In-Person Training.
    </p>
    <p>
        Starting in 2025,
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

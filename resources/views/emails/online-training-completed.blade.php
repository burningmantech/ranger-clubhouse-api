<x-html-email :isPublicEmail="true">
    <p>
        @if ($person->isPNV())
            Hello Potential Ranger Volunteer {{$person->callsign}},
        @elseif ($person->isAuditor())
            Hello Auditor {{@$person->first_name}},
        @else
            Hello Ranger {{$person->callsign}},
        @endif
    </p>
    <p>
        Congratulations! You have successfully completed the Ranger Online Course.
        You are cleared to sign up for the In-Person Training.
    </p>
    <p>
        Note, not all trainings may yet be announced.
    </p>
    @if ($person->isPNV())
        <p>
            <b>Visit the</b> <a href="https://ranger-clubhouse.burningman.org">Ranger Secret Clubhouse</a>
            <b>to see what additional steps you need to complete in order to become a Black Rock Ranger.</b>
        </p>
    @elseif ($person->isAuditor())
        <p>
            Visit the <a href="https://ranger-clubhouse.burningman.org">Ranger Secret Clubhouse</a>
            to audit the In-Person Training.
        </p>
        <p>
            <b>WARNING: You are an auditor, you are NOT on the path to becoming a Black Rock Ranger this year.</b>
        </p>
    @else
        <p>
            <b>All Rangers MUST complete the In-Person Training before being allowed to work a shift on playa.</b>
        </p>
        <p>
            In order to work certain Ranger special team shifts, you have to sign up for and attend the corresponding
            Advance Ranger Training(s).
        </p>
        <p>
            Visit the <a href="https://ranger-clubhouse.burningman.org">Ranger Secret Clubhouse</a>
            to sign up for the In-Person Training.
        </p>
    @endif
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

<x-html-email :isPublicEmail="true">
<p>
        Hello {{$person->callsign}},
    </p>

    <p>
        Congratulations! You have successfully completed Part 1 of Ranger Training (online).
        You are cleared to sign up for Part 2 of Ranger Training (face-to-face).
    </p>

    @if ($person->isPNV())
        <p>
            Visit the <a href="https://ranger-clubhouse.burningman.org">Ranger Secret Clubhouse</a>
            to see what additional tasks you need to complete in order to become a Black Rock Ranger.
        </p>
    @elseif ($person->isAuditor())
        <p>
            Visit the <a href="https://ranger-clubhouse.burningman.org">Ranger Secret Clubhouse</a>
            to sign up to audit Part 2 of Ranger Training.
        </p>
        <p>
            <b>NOTE: Since you are an auditor, you are NOT on the path to becoming a Black Rock Ranger this year.</b>
        </p>
    @else
        <p>
            All Rangers must complete Part 2 of Ranger Training (face-to-face) before being allowed to work a shift.
        </p>
        <p>
            Visit the <a href="https://ranger-clubhouse.burningman.org">Ranger Secret Clubhouse</a>
            to sign up for Part 2 of Ranger Training.
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

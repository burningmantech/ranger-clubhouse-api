<x-html-email>
    Hey {{$application->first_name}} {{$application->last_name}},

    <p>
        Thanks for considering the Rangers! We’ve reviewed your application.
    </p>

    <p>
        Unfortunately, we were unable to confirm your Regional Ranger experience.
    </p>
    @if (!empty($messageToUser))
        <p>
            The Ranger Volunteer Coordinators have left you a message:
        </p>
        <p>
            {!! nl2br($messageToUser) !!}
        </p>
    @endif
    <p>
        Please contact the Black Rock Ranger Volunteer Coordinators at
        <a href="mailto:ranger-vc-list@burningman.org">ranger-vc-list@burningman.org</a>
        if additional details can be supplied to clear up any confusion over your Regional Ranger experience.
    </p>
    <p>
        To review, you must meet the following qualifications below in order to apply to volunteer with the Black Rock Rangers.
    </p>
    <p>
        You must be at least eighteen years old and:
    </p>
    <b>&mdash; EITHER &mdash;</b>
    <ul>
        <li>
            have attended Burning Man at least twice with one of those years being in the last ten years.
        </li>
        <li>
            <b>OR:</b> have attended Burning Man at least once in the last ten years, as well as have participated
            as a Ranger at a sanctioned Burning Man regional event at least once in the last five years (or will have by
            April 5th of this year).
        </li>
    </ul>
    <p>
        Thanks again for your interest in the Rangers! Please re-apply when you can meet our qualifications. In the
        meantime, there are lots of great ways to volunteer at Burning Man that don't have these requirements:<br>
        <a href="https://burningman.org/event/volunteering/teams/">https://burningman.org/event/volunteering/teams/</a>
    </p>

    <p>
        See you in the dust!
    </p>

    <p>
        Your Friendly Black Rock Ranger Volunteer Coordinators
    </p>
    <b>Questions?</b> Email <a href="mailto:ranger-vc-list@burningman.org">ranger-vc-list@burningman.org</a>
</x-html-email>

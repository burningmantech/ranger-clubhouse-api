<x-html-email>
    Hey {{$application->first_name}} {{$application->last_name}},

    <p>
        Thanks for considering the Rangers! We've reviewed your application.
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
        <b>You must be at least eighteen years old and have attended Burning Man once in the last ten years.</b>
        Please note, 2020 and 2021 do not count towards the attendance qualification.
    </p>
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
    <x-vc-questions />
</x-html-email>

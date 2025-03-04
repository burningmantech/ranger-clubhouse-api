<x-html-email>
    Hey {{$application->first_name}} {{$application->last_name}},

    <p>
        Thanks for considering the Rangers! We’ve reviewed your application.
    </p>

    <p>
        When asked if you meet the minimum qualifications to volunteer with the Rangers, note that you must be at least
        18 years old by the time of your Alpha shift. These shifts take place on the Saturday before the gate opens, as
        well as Sunday, Monday, and Tuesday.
    </p>
    <p>
        Unfortunately, you do not meet the qualifications. In order to apply to volunteer
        with the Black Rock Rangers, <b style="color: #c90000;">you must be at least eighteen years old</b> and
        have attend Burning Man once in the last ten years. Please note, 2020 and 2021 do not count towards the
        attendance qualification.
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

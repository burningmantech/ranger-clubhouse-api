@php use App\Models\ProspectiveApplication; @endphp
<x-html-email>
    Hey {{$application->first_name}} {{$application->last_name}},

    <p>
        Thanks for considering the Rangers! We’ve reviewed your application.
    </p>

    <p>
        When asked whether you meet the minimum qualifications to volunteer with the Rangers, you responded:
        @if ($application->experience == ProspectiveApplication::EXPERIENCE_BRC2)
            <i>Attended Burning Man at least twice</i>
        @elseif ($application->experience == ProspectiveApplication::EXPERIENCE_BRC1R1)
            <i>Attend Burning Man at least once, and have worked as regional Ranger at a sanctioned regional event at
                least once</i>
        @else
            <i>Have never attended Burning Man</i>
        @endif
    </p>
    @if ($application->experience != ProspectiveApplication::EXPERIENCE_NONE)
        <p>
            In your Burner Profile, you have indicated you have
            @if (empty($application->qualifiedEvents()))
                not been to Burning Man.
            @else
                attended {{count($application->qualifiedEvents())}} Burning Man event(s):<br>
                {{implode(', ', $application->qualifiedEvents())}}
            @endif
        </p>
        <p>
            Note that, 2020 and 2021 do not count towards the attendance qualification for either Burning Man
            nor sanctioned regional events.
        </p>
    @endif
    <p>
        Unfortunately, it appears you do not meet the qualifications. In order to apply to volunteer
        with the Black Rock Rangers, you must be at least eighteen years old and:
    </p>
    <b>&mdash; EITHER &mdash;</b>
    <ul>
        <li>
            have attended Burning Man at least twice with one of those years being in the last ten
            years.
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

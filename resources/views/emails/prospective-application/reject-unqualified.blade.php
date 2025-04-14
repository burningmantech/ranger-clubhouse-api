@php use App\Models\ProspectiveApplication; @endphp
<x-html-email>
    <p>
        Hey {{$application->first_name}} {{$application->last_name}},
    </p>
    <p>
        Thanks for considering the Rangers! We've reviewed your application.
    </p>

    @if ($application->experience == ProspectiveApplication::EXPERIENCE_NONE)
        <p>
            In your Ranger Application, you have indicated you have never been to Burning Man.
        </p>
    @elseif (!count($application->qualifiedEvents()))
        <p>
            In your Ranger application, you have indicated you have been to Burning Man, but in your Burning Man
            Profile, no qualifying years were listed.
        </p>
        @if (count($application->oldEvents()))
            <p>
                The following years are ineligible for the attendance qualification as they are more than ten years ago:<br>
                {{implode(', ', $application->oldEvents())}}
            </p>
        @endif
        @if ($application->havePandemicYears())
            <p>
                Your Burner Profile does have either 2020 and/or 2021 listed. Unfortunately these years do not
                count towards the attendance qualification.
            </p>
        @endif

    @endif
    <p>
        Unfortunately, it appears you do not meet the qualifications. In order to apply to volunteer
        with the Black Rock Rangers, you must be at least eighteen years old and have attended Burning Man once in
        the last ten years. Please note, 2020 and 2021 do not count towards the attendance qualification.
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
        Your Black Rock Ranger Volunteer Coordinators
    </p>
    <x-vc-application-footer :application="$application" />
</x-html-email>

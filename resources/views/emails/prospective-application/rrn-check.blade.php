<x-html-email>
    <p>
        Hey Regional Ranger Network Team,
    </p>
    <p>
        Please help the Volunteer Coordinators verify the following person is a regional Ranger in good standing.
        Be sure to confirm the person has worked as a regional Ranger within the last 5 years.
    </p>

    <table class="table table-auto-width">
        <tbody>
        <tr>
            <td>Application ID</td>
            <td>A-{{$application->id}} (Salesforce ID {{$application->salesforce_name}})</td>
        </tr>
        <tr>
            <td>BRC Ranger Applicant</td>
            <td><b>{{$application->first_name}} {{$application->last_name}}</b></td>
        </tr>
        <tr>
            <td>
                Regional Callsign(s)
            </td>
            <td>
                @if (!empty($application->regional_callsign))
                    <b>{{$application->regional_callsign}}</b>
                @else
                    <i>No Regional callsigns stated.</i>
                @endif
            </td>
        </tr>
        <tr>
            <td>Regional Experience</td>
            <td>
                @if (!empty($application->regional_experience))
                    <b>{!! nl2br($application->regional_experience) !!}</b>
                @else
                    <i>Uh oh, the applicant did not list any Regional Experience. Perhaps a V.C. pushed the wrong
                        button? Let the V.C.s know something might be amiss.</i>
                @endif
            </td>
        </tr>
        </tbody>
    </table>
    <p>
        Once you have determined their regional Ranger's standing, or lack thereof, please respond to this email
        with what you have found out.
    </p>
    <p>
        Thank You,
    </p>
    <p>
        The V.C.s
    </p>
</x-html-email>

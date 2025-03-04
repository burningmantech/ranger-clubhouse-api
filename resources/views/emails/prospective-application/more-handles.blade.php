<x-html-email>
    <p>
        Hey {{$application->first_name}} {{$application->last_name}},
    </p>
    <p>
        The Ranger Handle Wranglers have reviewed the radio Ranger callsign options you have submitted, and sadly, we
        were unable to approve any of them.
    </p>
    <x-vc-application-on-hold />
    <p>
        Here is the message from the Ranger Handle Wranglers:
    </p>
    <p>
        {!! nl2br($messageToUser) !!}
    </p>
    <p>
        Reply to this message with <b>FIVE MORE HANDLES TO CONSIDER</b>.
    </p>
    <p>
        Please take a second to review the guidelines for Ranger radio callsigns (aka handles):<br>
        <a href="https://ranger-clubhouse.burningman.org/HandleAdvice">https://ranger-clubhouse.burningman.org/HandleAdvice</a>
    </p>

    <p>
        Your Friendly Black Rock Ranger Volunteer Coordinators
    </p>
    <x-vc-questions />
    Application ID A-{{$application->id}}
</x-html-email>

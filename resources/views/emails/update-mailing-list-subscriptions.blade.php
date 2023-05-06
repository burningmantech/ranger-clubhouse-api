<x-html-email>
    <p>Hello from the Clubhouse Bot,</p>
    <p>
        {{$person->callsign}} recently updated their email address in the Clubhouse and has requested
        the Ranger mailing lists be updated as well.
    </p>
    <p>
        Old email address: {{$oldEmail}}<br>
        New email address: {{$person->email}}<br>
    </p>
    <p>
        @if ($teams->isNotEmpty())
            The person is a member of the following Clubhouse Teams:<br>
            @foreach ($teams as $team)
                {{$team->title}}<br>
            @endforeach
        @else
            The person does not appear to be a member of any Clubhouse Team.
        @endif
    </p>
    @if (empty($additionalLists))
        <p>
            {{$person->callsign}} did not state if other mailing lists should be updated.
        </p>
    @else
        <p>
            {{$person->callsign}} left the following message about updating other Ranger mailing lists besides Allcom &amp; Announce:
        </p>
        <p>
            {!! nl2br(e($additionalLists)) !!}
        </p>
    @endif
    <p>
        You can use the link below to respond to {{$person->callsign}} at their new address.
    </p>
    <p>
        <a href="mailto:{{$person->email}}?subject=Ranger mailing list subscriptions">
            Send email to {{$person->callsign}} at {{$person->email}}
        </a>
    </p>
</x-html-email>

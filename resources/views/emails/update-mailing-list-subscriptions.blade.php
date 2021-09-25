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
        @if (empty($additionalLists))
            No additional mailing lists to update were given.
        @else
            The following mailing list(s) should be updated in addition to Allcom &amp; Announce:<br>
            {!! nl2br(e($additionalLists)) !!}
        @endif
    </p>
    <p>
        You can use the link below to respond to {{$person->callsign}} at their new address.
    </p>
    <p>
        <a href="mailto:{{$person->email}}?subject=Ranger mailing list subscriptions ">Send email to {{$person->callsign}}</a>
    </p>
</x-html-email>

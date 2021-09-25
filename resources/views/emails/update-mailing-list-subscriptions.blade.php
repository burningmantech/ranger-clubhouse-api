<x-html-email :isPublicEmail="true">
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
        You can use the link below to respond to {{$person->callsign}} at their new address.
    </p>
    <p>
        <a href="mailto:{{$person->email}}?subject=Ranger mailing list subscriptions updated">Send email to {{$person->callsign}}</a>
    </p>
</x-html-email>

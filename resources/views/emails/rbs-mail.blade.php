<x-html-email :isPublicEmail="true">
    <p>Hello from the Black Rock Rangers,</p>
    <p>
        * * * DO NOT REPLY TO THIS MESSAGE * * *
    </p>
    @if ($expiresAt)
        <p style="color:red;">
            If you have received this message after
            {{$expiresAt->toFormattedDayDateString()}} {{$expiresAt->toTimeString()}} (Pacific), please disregard it.
        </p>
    @endif
    <p>
        @hyperlinktext($rbsMessage)
    </p>
    <p>
        This message was sent through the Clubhouse. You may choose not to receive
        these messages by adjusting your Alert Preferences
        ("{{$alert->on_playa ? "On Playa" : "Pre-Event"}}: {{$alert->title}}")
        in the Clubhouse.
    </p>

    Thank you,<br>
    The Black Rock Rangers<br>
</x-html-email>

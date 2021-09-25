<x-html-email :isPublicEmail="true">
    <p>
        Dear Ranger {{$person->callsign}},
    </p>
    <p>
        * * * DO NOT REPLY TO THIS MESSAGE * * *
    </p>
    <p>
        You have a new Clubhouse message.
    </p>
    <p>
        From: {{$clubhouseFrom}}<br>
        Subject: {{$clubhouseSubject}}
    </p>
    <p>
        {{$clubhouseMessage}}
    </p>
    <hr>

    <p>
        This message was sent through the Black Rock Ranger Secret Clubhouse. You may choose not to receive
        these messages by adjusting your Alert Preferences ("Pre-Event: Clubhouse Messages" &amp; "On Playa: Clubhouse
        Messages").
    </p>

    <p>
        If you have received this message in error, let us know by emailing:<br>
        <a href="mailto:rangers@burningman.org">rangers@burningman.org</a>
    </p>

    <p>
        Thank you,
    </p>
    <p>
        The Black Rock Ranger Secret Clubhouse
    </p>
</x-html-email>
<x-html-email :isPublicEmail="true">
    <p>
        Dear Ranger {{$person->callsign}},
    </p>
    <p>
        <b>* * * DO NOT REPLY TO THIS MESSAGE * * *</b>
    </p>
    <p>
        Hello from the Black Rock Rangers Secret Clubhouse.
    </p>
    <p>
        Ranger {{$senderPerson->callsign}} has sent you a Contact Message through the Clubhouse. Please note,
        Contact Messages are NOT retained in the Clubhouse. The message is:
    </p>
    <hr>
    <p>
        {!! nl2br($contactMessage) !!}
    </p>
    <hr>

    <p>
        This message was sent through the Clubhouse. You may choose not to receive
        these messages by adjusting your Alert Preferences in the Clubhouse.
    </p>

    <p>
        Your email address has not been revealed to the sender. You are under no
        obligation to respond to the Ranger wishing to get in contact.
    </p>

    <p>
        If you have received this message in error, let us know by emailing:<br>
        <a href="mailto:rangers@burningman.org">rangers@burningman.org</a>
    </p>

    <p>
        Thank you,
    </p>
    <p>
        The Black Rock Rangers
    </p>
</x-html-email>

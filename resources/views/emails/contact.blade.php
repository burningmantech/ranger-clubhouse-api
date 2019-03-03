@component('html-email')
<p>
Dear Ranger {{$recipientPerson->callsign}},
</p>
<p>
* * * DO NOT REPLY TO THIS MESSAGE * * *
</p>
<p>
Hello from the Black Rock Rangers Secret Clubhouse.
</p>
<p>
Ranger {{$senderPerson->callsign}} has sent you a contact message:
</p>
<hr>
<p>
{{$contactMessage}}
</p>
<hr>

<p>
    This message was sent through the Clubhouse. You may choose not to receive
    these messages by adjusting your Alert Preferences in the Clubhouse.
</p>

<p>
    Your email address has not been revealed to the sender. Your are under no
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
@endcomponent

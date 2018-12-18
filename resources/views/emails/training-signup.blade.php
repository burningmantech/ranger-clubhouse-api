<html>
<body>
<p>Hello from the Black Rock Rangers,</p>
<p>
Congratulations! You are signed up for a Ranger training class.
</p>
<p>
<b>What:</b> {{$slot->position->title}} - {{$slot->description}}<br>
<b>Starts:</b> {{$slot->beginsHumanFormat}}<br>
<b>Ends: </b> {{$slot->endsHumanFormat}}
</p>
@if ($slot->url)
<p>
    <b>Additional Information:</b><br>
    {{$slot->url}}
</p>
@endif
<p>
An email message will be sent a few days before the training class happens
with details about the training location and what to bring.
</p>
<p>
If you are unable to attend the training class, PLEASE remove yourself from the schedule
by logging into the Ranger Secret Clubhouse, and clicking on the 'Schedule/Signups'
link in the left sidebar or under the 'Me' menu. Click the trash icon to delete
the session from your schedule.
</p>
<p>
    * * * DO NOT REPLY TO THIS MESSAGE * * *
</p>
<p>
Thank you,<br>
The Black Rock Rangers<br>
</p>
<p>
If you have received this message in error, please let us know by emailing
<a href="mailto:{{$vcEmail}}">{{$vcEmail}}</a>.
</p>
</body>
</html>

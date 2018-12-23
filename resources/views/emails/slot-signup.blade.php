@component('html-email')
<p>Hello from the Black Rock Rangers,</p>
<p>
    * * * DO NOT REPLY TO THIS MESSAGE * * *
</p>
<p>
Congratulations! You are signed up for a Ranger shift.
</p>
<p>
<b>What:</b> {{$slot->position->title}} - {{$slot->description}}<br>
<b>Starts:</b> {{$slot->beginsHumanFormat}}<br>
<b>Ends: </b> {{$slot->endsHumanFormat}}
</p>
@if ($slot->url)
<p>
    <b>Additional Information:</b><br>
    @hyperlinktext($slot->url)
</p>
@endif
<p>
If you are unable to work this shift, PLEASE remove yourself from the schedule
by logging into the Ranger Secret Clubhouse, and clicking on the 'Schedule/Signups'
link in the left sidebar or under the 'Me' menu. Click the trash icon to delete
the session from your schedule.
</p>
<p>
Thank you,<br>
The Black Rock Rangers<br>
</p>
@endcomponent

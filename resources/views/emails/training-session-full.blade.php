<x-html-email>
<p>
Heads up! A training session has reached capacity.
</p>
<p>
<b>What:</b> {{$slot->position->title}} - {{$slot->description}}<br>
<b>When:</b> {{$slot->beginsHumanFormat}}<br>
<b>Signups:</b> {{$signedUp}}<br>
<b>Max:</b> {{$slot->max}}<br>
</p>
<p>
Your humble servant,<br>
<br>
The Clubhouse Bot
</p>
</x-html-email>

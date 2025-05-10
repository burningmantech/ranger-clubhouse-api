<x-html-email>
    <p>
        Hello from the Clubhouse,
    </p>
    <p>
        This is an automated reminder about a recent training session. No results have been recorded in over 24 hours.
        Please enter the results as soon as possible, as trainees are waiting to learn whether they passed.
    </p>
    <p>
        <b>What:</b> {{$slot->position->title}} - {{$slot->description}}<br>
        <b>Time:</b> {{$slot->beginsHumanFormat}}<br>
    </p>
    <p>
        Please ignore this message if this has been taken care of.
    </p>
    <p>
        Forever your humble digital servant,<br>
        The Clubhouse Bot
    </p>
</x-html-email>

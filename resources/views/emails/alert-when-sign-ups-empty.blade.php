<x-html-email>
    <p>
        Heads up! A {{$position->title}} shift has become empty.
    </p>
    <p>
        <b>What:</b> {{$position->title}} - {{$slot->description}}<br>
        <b>When:</b> {{$slot->beginsHumanFormat}}<br>
        <b>Mentee Signups:</b> {{$menteeSignUps}}<br>
    </p>
    <p>
        Your humble servant,<br>
        <br>
        The Clubhouse Bot
    </p>
</x-html-email>

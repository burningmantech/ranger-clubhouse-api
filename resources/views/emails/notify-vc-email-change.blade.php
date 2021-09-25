<x-html-email>
<p>
  Dear VCs,
</p>

<p>
  A prospective has changed their email:
</p>

<p>
  {{$person->callsign}} &lt;{{$person->first_name}} {{$person->last_name}}&gt;<br>
  old email <a href="mailto:{{$oldEmail}}">{{$oldEmail}}</a><br>
  new email <a href="mailto:{{$person->email}}">{{$person->email}}</a>
</p>

<p>
  Forever your humble servant,<br>
  <br>
  The Clubhouse
</p>
</x-html-email>

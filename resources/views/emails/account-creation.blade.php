<x-html-email>
<p>
@if ($status == 'failed')
Failed to create an account ({{$details}}) for:
@else
Successfully created an account for: {{$person->callsign}}
@endif
{{$person->first_name}} {{$person->last_name}} <a href="mailto:{{$person->email}}">{{$person->email}}</a> who intends to {{$intent}}
</p>
<p>
Your humble servant,<br>
<br>
The Clubhouse
</p>
</x-html-email>

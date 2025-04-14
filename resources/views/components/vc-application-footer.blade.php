<x-vc-questions/>
Application ID A-{{$application->id}}<br>
@if ($application->assigned_person)
    Assigned #{{$application->assigned_person->id}} {{$application->assigned_person->callsign[0]}}
@else
    (available)
@endif

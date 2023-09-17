<x-html-email>
    <h2>Ranger WAP Report for {{date('Y-m-d')}}</h2>
    @if (empty($people))
        No Rangers found who may need WAPs.
    @else
        The following Rangers may need WAPs:
        <table class="table table-sm table-striped">
            <thead>
            <tr>
                <th>Person</th>
                <th>Status</th>
                <th>Has SPT?</th>
                <th>Years worked {{$startYear}} &amp; later</th>
                <th>Schedule</th>
            </tr>
            </thead>

            <tbody>
            @foreach ($people as $person)
                <tr>
                    <td>
                        <a href="https://ranger-clubhouse.burningman.org/client/person/{{$person->id}}">{{$person->callsign}}</a>
                    </td>
                    <td>{{$person->status}}</td>
                    <td>
                        @if ($person->has_rpt)
                            @foreach ($person->tickets as $ticket)
                                RAD-{{$ticket->id}} {{$ticket->getShortTypeLabel()}} {{$ticket->status}}<br>
                            @endforeach
                        @else
                            NO SPT
                        @endif
                    </td>
                    <td>
                        @if (empty($person->years))
                            No years
                        @else
                            {{implode(', ', $person->years)}}
                        @endif
                    </td>
                    <td>
                        @if (count($person->schedule))
                            {{count($person->schedule)}} sign up(s):<br>
                            @foreach($person->schedule as $slot)
                                {{$slot->begins}} {{$slot->position_title}} {{$slot->description}}<br>
                            @endforeach
                        @else
                            No Sign Ups
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</x-html-email>

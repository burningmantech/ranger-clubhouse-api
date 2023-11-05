<x-html-email>
    <h2>Automatic Timesheet Sign Outs</h2>
    The following timesheet entries have been automatically signed out of and the duration capped at {{$hourCap}} hours.
    <table class="table table-sm table-striped">
        <thead>
        <tr>
            <th>Person</th>
            <th>Position</th>
            <th>On Duty</th>
            <th>Off Duty</th>
        </tr>
        </thead>

        <tbody>
        @foreach ($entries as $entry)
            <tr>
                <td>
                    <a href="https://ranger-clubhouse.burningman.org/person/{{$entry->person_id}}/timesheet">{{$entry->person->callsign}}</a>
                </td>
                <td>
                    {{$entry->position->title}}
                </td>
                <td>
                    {{$entry->on_duty}}
                </td>
                <td>
                    {{$entry->off_duty}}
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</x-html-email>

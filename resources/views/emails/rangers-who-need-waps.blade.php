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
            </tr>
            </thead>

            <tbody>
            @foreach ($people as $person)
                <tr>
                    <td>{{$person->callsign}}</td>
                    <td>{{$person->status}}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</x-html-email>

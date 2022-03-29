<x-html-email>
    <h2>Ranger WAP Report for {{date('Y-m-d')}}</h2>
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
</x-html-email>

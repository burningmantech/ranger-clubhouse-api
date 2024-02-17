<x-html-email>
    <p>
        Hello V.C.s,
    </p>
    <p>
        The following applications have been approved, and are awaiting for someone to press some buttons to turn these
        into prospective accounts.
    </p>
    <table class="table table-width-auto">
        <thead>
        <tr>
            <th>Application</th>
            <th>Name</th>
            <th>Approved Callsign</th>
        </tr>
        </thead>
        <tbody>
        @foreach($applications as $app)
            <tr>
                <td>A-{{$app->id}}</td>
                <td>{{$app->first_name}} {{$app->last_name}}</td>
                <td>{{$app->approved_handle}}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <p>
        Forever your digital robotic servant,<br>
        The Clubhouse Bot
    </p>
</x-html-email>
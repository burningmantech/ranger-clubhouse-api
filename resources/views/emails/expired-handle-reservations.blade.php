<x-html-email>
    <p>
        Hello Volunteer Coordinators,
    </p>
    <p>
        The following expired reserved handles have been deleted.
    </p>
    <table class="table table-sm table-striped table-width-auto" style="margin-bottom: 20px">
        <thead>
        <tr>
            <th>Handle</th>
            <th>Type</th>
            <th>Reason</th>
            <th>Expired On</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($handles as $row)
            <tr>
                <td>{{$row['handle']}}</td>
                <td>{{$row['type']}}</td>
                <td>{{$row['reason']}}</td>
                <td>{{$row['expires_on']}}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <p>
        Forever your humble digital servant,<br>
        The Clubhouse Bot
    </p>
</x-html-email>

<x-html-email>
    <p>
        Hello from the Clubhouse Bot,
    </p>

    <p>
        A Ranger has walked a non-training shift and has been restored to active status.
    </p>

    <table class="table">
        <tbody>
        <tr>
            <th>Who</th>
            <td>{{$person->callsign}}</td>
        </tr>
        <tr>
            <th>Old Status</th>
            <td>{{$oldStatus}}</td>
        </tr>
        <tr>
            <th>Converted when</th>
            <td>{{$timeOfConversion->format('l M d @ H:i')}}</td>
        </tr>
        <tr>
            <th>Position worked</th>
            <td>{{$positionTitle}}</td>
        </tr>
        <tr>
            <th>Shift ended by</th>
            <td>{{$workerCallsign}}</td>
        </tr>
        </tbody>
    </table>
    <p>
        Forever your humble servant,<br>
        <br>
        The Clubhouse
    </p>
</x-html-email>

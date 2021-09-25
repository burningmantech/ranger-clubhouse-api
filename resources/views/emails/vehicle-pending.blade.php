<x-html-email>
    <p>
        Hello Vehicle Request Reviewer,
    </p>
    <p>
        One or more vehicles are <a href="https://ranger-clubhouse.burningman.org/client/reports/vehicle-registry">pending reviewed.</a>
    </p>
    <p>
    <table class="table table-sm table-striped">
        <thead>
        <tr>
            <th>Person</th>
            <th>Vehicle</th>
            <th>Submitted</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($pending as $v)
            <tr>
                <td>{{$v->person->callsign}}</td>
                <td>{{$v->vehicle_year}} {{$v->vehicle_make}} {{$v->vehicle_model}} {{$v->vehicle_color}}</td>
                <td>{{$v->created_at}}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </p>
    <p>
        Forever your humble digital servant,<br>
        The Clubhouse Bot
    </p>
</x-html-email>
